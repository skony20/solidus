<?php

declare(strict_types=1);

namespace App\Module\Account\Repository;

use App\Module\Account\Entity\User;
use DateTimeImmutable;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Query\Query;

/**
 * Dostep do tabeli `users`.
 *
 * Logowanie odbywa sie zanim istnieje kontekst tenanta, dlatego to
 * repozytorium przyjmuje tenant_id jawnie w argumentach zamiast czytac go
 * z {@see \App\Shared\Tenant\TenantContext}. Wszystkie zapytania i tak
 * zawsze zawieraja warunek po tenancie.
 */
final readonly class UserRepository
{
    public const TABLE = 'users';

    public function __construct(
        private ConnectionInterface $db,
    ) {}

    public function findById(int $id): ?User
    {
        $row = (new Query($this->db))->from(self::TABLE)->where(['id' => $id])->one();

        return $row === null ? null : User::fromRow($row);
    }

    public function findByEmail(int $tenantId, string $email): ?User
    {
        $row = (new Query($this->db))
            ->from(self::TABLE)
            ->where(['tenant_id' => $tenantId, 'email' => mb_strtolower($email)])
            ->one();

        return $row === null ? null : User::fromRow($row);
    }

    public function emailExists(int $tenantId, string $email): bool
    {
        return (new Query($this->db))
            ->from(self::TABLE)
            ->where(['tenant_id' => $tenantId, 'email' => mb_strtolower($email)])
            ->exists();
    }

    /**
     * @param string[] $roles
     */
    public function create(
        int $tenantId,
        string $email,
        string $plainPassword,
        string $name,
        array $roles = ['owner'],
    ): User {
        $now = new DateTimeImmutable();
        $hash = password_hash($plainPassword, PASSWORD_DEFAULT);

        $this->db->createCommand()->insert(self::TABLE, [
            'tenant_id' => $tenantId,
            'email' => mb_strtolower($email),
            'password_hash' => $hash,
            'name' => $name,
            'roles' => json_encode(array_values($roles), JSON_THROW_ON_ERROR),
            'is_active' => 1,
            'created_at' => $now->format('Y-m-d H:i:s.u'),
            'updated_at' => $now->format('Y-m-d H:i:s.u'),
        ])->execute();

        return new User(
            id: (int) $this->db->getLastInsertId(),
            tenantId: $tenantId,
            email: mb_strtolower($email),
            passwordHash: $hash,
            name: $name,
            roles: array_values($roles),
            isActive: true,
            createdAt: $now,
            updatedAt: $now,
        );
    }
}
