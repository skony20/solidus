<?php

declare(strict_types=1);

namespace App\Module\Account\Repository;

use App\Module\Account\Entity\Tenant;
use DateTimeImmutable;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Query\Query;

/**
 * Dostep do tabeli `tenants`.
 *
 * Jako jedyne repozytorium NIE uzywa traitu TenantScoped - tabela tenantow
 * stoi ponad podzialem na tenantow i jest czytana zanim wiadomo, o ktory
 * tenant chodzi (np. przy rejestracji).
 */
final readonly class TenantRepository
{
    public const TABLE = 'tenants';

    public function __construct(
        private ConnectionInterface $db,
    ) {}

    public function findById(int $id): ?Tenant
    {
        $row = (new Query($this->db))->from(self::TABLE)->where(['id' => $id])->one();

        return $row === null ? null : Tenant::fromRow($row);
    }

    public function findBySlug(string $slug): ?Tenant
    {
        $row = (new Query($this->db))->from(self::TABLE)->where(['slug' => $slug])->one();

        return $row === null ? null : Tenant::fromRow($row);
    }

    public function slugExists(string $slug): bool
    {
        return (new Query($this->db))->from(self::TABLE)->where(['slug' => $slug])->exists();
    }

    public function create(string $name, string $slug, string $plan = 'starter'): Tenant
    {
        $createdAt = new DateTimeImmutable();

        $this->db->createCommand()->insert(self::TABLE, [
            'name' => $name,
            'slug' => $slug,
            'plan' => $plan,
            'created_at' => $createdAt->format('Y-m-d H:i:s.u'),
        ])->execute();

        return new Tenant(
            id: (int) $this->db->getLastInsertId(),
            name: $name,
            slug: $slug,
            plan: $plan,
            createdAt: $createdAt,
        );
    }
}
