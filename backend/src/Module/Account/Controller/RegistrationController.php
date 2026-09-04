<?php

declare(strict_types=1);

namespace App\Module\Account\Controller;

use App\Module\Account\Repository\TenantRepository;
use App\Module\Account\Repository\UserRepository;
use App\Shared\Http\ApiController;
use App\Shared\Http\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Db\Connection\ConnectionInterface;

/**
 * Zalozenie nowego biura rachunkowego wraz z kontem wlasciciela.
 *
 * Tenant i pierwszy uzytkownik powstaja w jednej transakcji - biuro bez
 * mozliwosci zalogowania sie byloby smieciem w bazie.
 */
final readonly class RegistrationController extends ApiController
{
    public function __construct(
        JsonResponse $json,
        private ConnectionInterface $db,
        private TenantRepository $tenants,
        private UserRepository $users,
    ) {
        parent::__construct($json);
    }

    /**
     * POST /api/auth/register
     * Body: {tenantName, email, password, name}
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
            $user = $this->users->create($tenant->id, $email, $password, $name, ['owner']);
            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();

            throw $e;
        }

        return $this->json->created([
            'tenant' => $tenant->toArray(),
            'user' => $user->toArray(),
        ]);
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
}
