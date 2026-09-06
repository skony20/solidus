<?php

declare(strict_types=1);

namespace App\Module\Account\Controller;

use App\Module\Account\Repository\TenantRepository;
use App\Module\Account\Repository\UserRepository;
use App\Module\Account\Service\EmailVerificationException;
use App\Module\Account\Service\EmailVerificationService;
use App\Module\Account\Service\RefreshCookie;
use App\Shared\Auth\JwtService;
use App\Shared\Http\ApiController;
use App\Shared\Http\JsonResponse;
use App\Shared\Mail\MailerException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Db\Connection\ConnectionInterface;

/**
 * Zalozenie nowego biura rachunkowego wraz z kontem wlasciciela.
 *
 * Tenant i pierwszy uzytkownik powstaja w jednej transakcji - biuro bez
 * mozliwosci zalogowania sie byloby smieciem w bazie. Konto jest jednak
 * NIEZWERYFIKOWANE: logowanie odblokowuje sie dopiero po podaniu kodu
 * wyslanego na adres e-mail (patrz EmailVerificationService).
 */
final readonly class RegistrationController extends ApiController
{
    public function __construct(
        JsonResponse $json,
        private ConnectionInterface $db,
        private TenantRepository $tenants,
        private UserRepository $users,
        private EmailVerificationService $verification,
        private JwtService $jwtService,
        private RefreshCookie $refreshCookie,
    ) {
        parent::__construct($json);
    }

    /**
     * POST /api/auth/register
     * Body: {tenantName, email, password, name}
     *
     * Odpowiedz 201 nie zawiera tokenow - konto trzeba najpierw potwierdzic
     * kodem. Pole `emailSent` mowi frontowi, czy wiadomosc udalo sie wyslac
     * (jesli nie - uzytkownik uzyje "wyslij ponownie").
     */
    public function register(ServerRequestInterface $request): ResponseInterface
    {
        $body = $this->body($request);

        $tenantName = trim((string) ($body['tenantName'] ?? ''));
        $email = trim((string) ($body['email'] ?? ''));
        $password = (string) ($body['password'] ?? '');
        $name = trim((string) ($body['name'] ?? ''));

        $errors = [];

        if ($tenantName === '') {
            $errors['tenantName'][] = 'Nazwa biura jest wymagana.';
        }
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'][] = 'Podaj poprawny adres e-mail.';
        }
        if (mb_strlen($password) < 10) {
            $errors['password'][] = 'Haslo musi miec co najmniej 10 znakow.';
        }
        if ($name === '') {
            $errors['name'][] = 'Imie i nazwisko sa wymagane.';
        }

        if ($errors !== []) {
            return $this->json->unprocessable('Dane sa niepoprawne.', $errors);
        }

        $slug = $this->uniqueSlug($tenantName);

        $transaction = $this->db->beginTransaction();

        try {
            $tenant = $this->tenants->create($tenantName, $slug);
            $user = $this->users->create($tenant->id, $email, $password, $name, ['owner'], emailVerified: false);
            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();

            throw $e;
        }

        // Wysylka POZA transakcja: jesli SMTP zawiedzie, konto i tak istnieje,
        // a uzytkownik poprosi o nowy kod. Nie chcemy wycofywac rejestracji
        // z powodu chwilowej awarii poczty.
        $emailSent = true;

        try {
            $this->verification->startForNewUser($user, $tenant);
        } catch (MailerException) {
            $emailSent = false;
        }

        return $this->json->created([
            'tenant' => ['name' => $tenant->name, 'slug' => $tenant->slug],
            'email' => $user->email,
            'verificationRequired' => true,
            'emailSent' => $emailSent,
        ]);
    }

    /**
     * POST /api/auth/verify-email
     * Body: {tenant: "slug-biura", email, code}
     *
     * Po poprawnym kodzie konto jest aktywne i od razu zalogowane - odpowiedz
     * ma ten sam ksztalt co /api/auth/login (access token + ciasteczko refresh).
     */
    public function verifyEmail(ServerRequestInterface $request): ResponseInterface
    {
        $body = $this->body($request);
        $slug = trim((string) ($body['tenant'] ?? ''));
        $email = trim((string) ($body['email'] ?? ''));
        $code = trim((string) ($body['code'] ?? ''));

        if ($slug === '' || $email === '' || $code === '') {
            return $this->json->unprocessable('Podaj biuro, e-mail i kod.');
        }

        $tenant = $this->tenants->findBySlug($slug);
        $user = $tenant === null ? null : $this->users->findByEmail($tenant->id, $email);

        if ($tenant === null || $user === null) {
            return $this->json->unprocessable(
                EmailVerificationException::accountNotFound()->getMessage(),
                ['reason' => [EmailVerificationException::accountNotFound()->reason]],
            );
        }

        try {
            $verifiedUser = $this->verification->verify($user, $code);
        } catch (EmailVerificationException $e) {
            return $this->json->unprocessable($e->getMessage(), ['reason' => [$e->reason]]);
        }

        $tokens = $this->jwtService->issue(
            userId: (int) $verifiedUser->id,
            tenantId: $tenant->id,
            roles: $verifiedUser->roles,
            userAgent: $request->getHeaderLine('User-Agent') ?: null,
            ip: $this->clientIp($request),
        );

        $response = $this->json->ok([
            'accessToken' => $tokens->accessToken,
            'expiresIn' => $tokens->accessExpiresIn,
            'user' => $verifiedUser->toArray(),
            'tenant' => $tenant->toArray(),
        ]);

        return $this->refreshCookie->attach($response, $tokens->refreshToken);
    }

    /**
     * POST /api/auth/resend-code
     * Body: {tenant: "slug-biura", email}
     */
    public function resendCode(ServerRequestInterface $request): ResponseInterface
    {
        $body = $this->body($request);
        $slug = trim((string) ($body['tenant'] ?? ''));
        $email = trim((string) ($body['email'] ?? ''));

        if ($slug === '' || $email === '') {
            return $this->json->unprocessable('Podaj biuro i e-mail.');
        }

        $tenant = $this->tenants->findBySlug($slug);
        $user = $tenant === null ? null : $this->users->findByEmail($tenant->id, $email);

        if ($tenant === null || $user === null) {
            $e = EmailVerificationException::accountNotFound();

            return $this->json->unprocessable($e->getMessage(), ['reason' => [$e->reason]]);
        }

        try {
            $this->verification->resend($tenant, $user);
        } catch (EmailVerificationException $e) {
            return $this->json->unprocessable($e->getMessage(), ['reason' => [$e->reason]]);
        } catch (MailerException) {
            return $this->json->error(502, 'Nie udalo sie wyslac wiadomosci. Sprobuj za chwile.');
        }

        return $this->json->ok(['status' => 'sent']);
    }

    /**
     * Slug musi byc unikalny globalnie, bo trafia do adresow. Przy kolizji
     * doklejamy licznik: "biuro-nowak", "biuro-nowak-2", ...
     */
    private function uniqueSlug(string $name): string
    {
        $base = $this->slugify($name);
        $slug = $base;
        $suffix = 1;

        while ($this->tenants->slugExists($slug)) {
            $suffix++;
            $slug = $base . '-' . $suffix;
        }

        return $slug;
    }

    private function slugify(string $value): string
    {
        $map = [
            'ą' => 'a', 'ć' => 'c', 'ę' => 'e', 'ł' => 'l', 'ń' => 'n',
            'ó' => 'o', 'ś' => 's', 'ź' => 'z', 'ż' => 'z',
        ];

        $value = strtr(mb_strtolower($value), $map);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        $value = trim($value, '-');

        return $value === '' ? 'biuro' : mb_substr($value, 0, 80);
    }

    private function clientIp(ServerRequestInterface $request): ?string
    {
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? null;

        return is_string($ip) && $ip !== '' ? $ip : null;
    }
}
