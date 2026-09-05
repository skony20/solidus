<?php

declare(strict_types=1);

namespace App\Module\Platform\Repository;

use App\Module\Account\Entity\Tenant;
use App\Module\Account\Entity\TenantStatus;
use App\Module\Platform\Entity\TenantOverview;
use Yiisoft\Db\Connection\ConnectionInterface;

/**
 * Odczyt biur z punktu widzenia operatora Solidusa - lista i szczegoly na
 * ekran panelu administracyjnego.
 *
 * SWIADOMIE OSOBNO OD {@see \App\Module\Account\Repository\TenantRepository}.
 * Tamto repozytorium obsluguje logowanie i rejestracje - ma zostac chude
 * i nie ryzykowac, ze ktos doda do niego zapytanie zwracajace dane innych
 * biur. To repozytorium istnieje wylacznie po to, zeby administrator systemu
 * (rola platform_admin) mogl przejrzec WSZYSTKIE biura naraz - i nazwa klasy
 * ma to od razu zdradzac, zamiast chowac sie w ogolnym TenantRepository.
 *
 * Zapytania licza agregaty (liczba uzytkownikow, ostatnia platnosc) przez
 * skorelowane podzapytania, a nie JOIN + GROUP BY - JOIN do dwoch tabel
 * jeden-do-wielu (users, tenant_payments) naraz pomnozylby wiersze przed
 * agregacja i dalby zle liczby bez zadnego bledu SQL.
 */
final readonly class TenantAdminRepository
{
    public function __construct(
        private ConnectionInterface $db,
    ) {}

    /**
     * @return TenantOverview[]
     */
    public function findAll(?string $search, ?TenantStatus $status, int $limit, int $offset): array
    {
        [$where, $params] = $this->buildFilter($search, $status);

        $sql = $this->baseSelect() . "
            {$where}
            ORDER BY t.created_at DESC
            LIMIT {$limit} OFFSET {$offset}
        ";

        $rows = $this->db->createCommand($sql, $params)->queryAll();

        return array_map($this->rowToOverview(...), $rows);
    }

    public function count(?string $search, ?TenantStatus $status): int
    {
        [$where, $params] = $this->buildFilter($search, $status);

        return (int) $this->db
            ->createCommand("SELECT COUNT(*) FROM tenants t {$where}", $params)
            ->queryScalar();
    }

    public function findById(int $id): ?TenantOverview
    {
        $sql = $this->baseSelect() . ' WHERE t.id = :id';
        $row = $this->db->createCommand($sql, ['id' => $id])->queryOne();

        return $row === null ? null : $this->rowToOverview($row);
    }

    /**
     * Metadane pracownikow biura - TYLKO to, co identyfikuje konto, zero
     * danych merytorycznych. Operator widzi, kto ma dostep, nie co ten ktos
     * robi w systemie.
     *
     * @return array<int, array<string, mixed>>
     */
    public function usersFor(int $tenantId): array
    {
        $sql = 'SELECT id, email, name, roles, is_active, created_at
                FROM users WHERE tenant_id = :tid ORDER BY created_at ASC';

        $rows = $this->db->createCommand($sql, ['tid' => $tenantId])->queryAll();

        return array_map(static fn(array $row): array => [
            'id' => (int) $row['id'],
            'email' => (string) $row['email'],
            'name' => (string) $row['name'],
            'roles' => array_values(array_filter(
                (array) (json_decode((string) $row['roles'], true) ?: []),
                static fn(mixed $r): bool => is_string($r),
            )),
            'isActive' => (bool) $row['is_active'],
            'createdAt' => (new \DateTimeImmutable((string) $row['created_at']))->format(DATE_ATOM),
        ], $rows);
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function buildFilter(?string $search, ?TenantStatus $status): array
    {
        $conditions = [];
        $params = [];

        if ($search !== null && $search !== '') {
            $conditions[] = '(t.name LIKE :search OR t.slug LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }

        if ($status !== null) {
            $conditions[] = 't.status = :status';
            $params['status'] = $status->value;
        }

        $where = $conditions === [] ? '' : 'WHERE ' . implode(' AND ', $conditions);

        return [$where, $params];
    }

    private function baseSelect(): string
    {
        return "
            SELECT
                t.*,
                pp.code AS plan_code,
                pp.name AS plan_name,
                (SELECT COUNT(*) FROM users u WHERE u.tenant_id = t.id) AS user_count,
                (SELECT MAX(tp.period_end) FROM tenant_payments tp WHERE tp.tenant_id = t.id) AS paid_until
            FROM tenants t
            LEFT JOIN pricing_plans pp ON pp.id = t.pricing_plan_id
        ";
    }

    /**
     * @param array<string, mixed> $row
     */
    private function rowToOverview(array $row): TenantOverview
    {
        return new TenantOverview(
            tenant: Tenant::fromRow($row),
            planCode: $row['plan_code'] !== null ? (string) $row['plan_code'] : null,
            planName: $row['plan_name'] !== null ? (string) $row['plan_name'] : null,
            userCount: (int) $row['user_count'],
            paidUntil: $row['paid_until'] !== null ? new \DateTimeImmutable((string) $row['paid_until']) : null,
        );
    }
}
