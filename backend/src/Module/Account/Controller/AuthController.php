<?php

declare(strict_types=1);

namespace App\Module\Account\Controller;

use App\Module\Account\Repository\TenantRepository;
use App\Module\Account\Repository\UserRepository;
use App\Module\Account\Service\RefreshCookie;
use App\Shared\Auth\InvalidTokenException;
use App\Shared\Auth\JwtService;
use App\Shared\Http\ApiController;
use App\Shared\Http\JsonResponse;
use App\Shared\Tenant\TenantMiddleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Logowanie, odswiezanie sesji, wylogowanie i "kim jestem".
 *
 * Kontroler nie zna JWT - deleguje wszystko do {@see JwtService}. Jego rola to
 * wylacznie warstwa transportu: przelozyc cialo zadania na wywolanie serwisu
 * i schowac refresh token w ciasteczku. Klient mobilny bedzie mial wlasny
 * kontroler czytajacy refresh token z ciala zadania, ale ten sam serwis.
 */
final readonly class AuthController extends ApiController
{
    public function __construct(
        JsonResponse $json,
        private JwtService $jwtService,
        private UserRepository $users,
        private TenantRepository $tenants,
        private RefreshCookie $refreshCookie,
    ) {
        parent::__construct($json);
    }

    /**
     * POST /api/auth/login
     * Body: {tenant: "slug-biura", email: "...", password: "..."}
     *
     * Slug biura jest czescia danych logowania, bo e-mail jest unikalny
     * w obrebie tenanta - ta sama osoba moze pracowac w dwoch biurach.
     */
    public function login(ServerRequestInterface $request): ResponseInterface
    {
        $body = $this->body($request);
        $slug = trim((string) ($body['tenant'] ?? ''));
        $email = trim((string) ($body['email'] ?? ''));
        $password = (string) ($body['password'] ?? '');

        if ($slug === '' || $email === '' || $password === '') {
            return $this->json->unprocessable('Podaj biuro, e-mail i haslo.');
        }

        $tenant = $this->tenants->findBySlug($slug);
        $user = $tenant === null ? null : $this->users->findByEmail($tenant->id, $email);

        // Jeden komunikat dla wszystkich przyczyn - inaczej formularz logowania
        // staje sie narzedziem do sprawdzania, czy dany e-mail istnieje.
        if ($user === null || !$user->isActive || !$user->verifyPassword($password)) {
            return $this->json->unauthorized('Nieprawidlowe dane logowania.');
        }

        $tokens = $this->jwtService->issue(
            userId: (int) $user->id,
            tenantId: $tenant->id,
            roles: $user->roles,
            userAgent: $request->getHeaderLine('User-Agent') ?: null,
            ip: $this->clientIp($request),
        );

        $response = $this->json->ok([
            'accessToken' => $tokens->accessToken,
            'expiresIn' => $tokens->accessExpiresIn,
            'user' => $user->toArray(),
            'tenant' => $tenant->toArray(),
        ]);

        return $this->refreshCookie->attach($response, $tokens->refreshToken);
    }

    /**
     * POST /api/auth/refresh
     *
     * Refresh token przychodzi z ciasteczka (przegladarka) albo z ciala
     * zadania (przyszly klient mobilny). Stary token jest uniewazniany -
     * rotacja sprawia, ze wykradziony token traci wartosc po pierwszym
     * odswiezeniu przez prawowitego wlasciciela.
     */
    public function refresh(ServerRequestInterface $request): ResponseInterface
    {
        $body = $this->body($request);
        $token = $this->refreshCookie->read($request)
            ?? (isset($body['refreshToken']) ? (string) $body['refreshToken'] : null);

        if ($token === null) {
            return $this->json->unauthorized('Brak tokenu odswiezajacego.');
        }

        try {
            $userId = $this->jwtService->refreshTokenSubject($token);
            $user = $this->users->findById($userId);

            if ($user === null || !$user->isActive) {
                return $this->json->unauthorized('Konto jest nieaktywne.');
            }

            // Role czytamy z bazy, nie z tokenu - odebranie uprawnien ma
            // zadzialac przy najblizszym odswiezeniu, bez czekania 30 dni.
            $tokens = $this->jwtService->refresh(
                refreshToken: $token,
                roles: $user->roles,
                userAgent: $request->getHeaderLine('User-Agent') ?: null,
                ip: $this->clientIp($request),
            );
        } catch (InvalidTokenException $e) {
            return $this->json->unauthorized($e->getMessage());
        }

        $response = $this->json->ok([
            'accessToken' => $tokens->accessToken,
            'expiresIn' => $tokens->accessExpiresIn,
            'user' => $user->toArray(),
        ]);

        return $this->refreshCookie->attach($response, $tokens->refreshToken);
    }

    /**
     * POST /api/auth/logout
     */
    public function logout(ServerRequestInterface $request): ResponseInterface
    {
        $token = $this->refreshCookie->read($request);

        if ($token !== null) {
            $this->jwtService->revokeRefreshToken($token);
        }

        return $this->refreshCookie->clear($this->json->ok(['status' => 'ok']));
    }

    /**
     * GET /api/auth/me - endpoint chroniony przez TenantMiddleware.
     */
    public function me(ServerRequestInterface $request): ResponseInterface
    {
        $identity = $request->getAttribute(TenantMiddleware::REQUEST_ATTRIBUTE);

        if ($identity === null) {
            return $this->json->unauthorized();
        }

        $user = $this->users->findById($identity->userId);
        $tenant = $this->tenants->findById($identity->tenantId);

        if ($user === null || $tenant === null) {
            return $this->json->unauthorized('Konto nie istnieje.');
        }

        return $this->json->ok([
            'user' => $user->toArray(),
            'tenant' => $tenant->toArray(),
        ]);
    }

    private function clientIp(ServerRequestInterface $request): ?string
    {
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? null;

        return is_string($ip) && $ip !== '' ? $ip : null;
    }
}
