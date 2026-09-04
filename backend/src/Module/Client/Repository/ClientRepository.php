<?php

declare(strict_types=1);

namespace App\Module\Client\Repository;

use App\Module\Client\Entity\Client;
use App\Shared\Tenant\TenantContext;
use App\Shared\Tenant\TenantScoped;
use DateTimeImmutable;
use Yiisoft\Db\Connection\ConnectionInterface;

/**
 * Dostep do tabeli `clients`.
 *
 * Kazda metoda przechodzi przez {@see TenantScoped::scopedQuery()} albo
 * {@see TenantScoped::tenantCondition()}, wiec zapytanie bez filtra po
 * tenancie po prostu nie powstaje. To jest miejsce, w ktorym izolacja
 * danych miedzy biurami jest egzekwowana.
 */
final readonly class ClientRepository
{
    use TenantScoped;

    public const TABLE = 'clients';

    public function __construct(
        private ConnectionInterface $db,
        private TenantContext $tenantContext,
    ) {}

    /**
     * @return Client[]
     */
    public function findAll(?string $search = null, ?string $status = null, int $limit = 100, int $offset = 0): array
    {
        $query = $this->scopedQuery()->orderBy(['name' => SORT_ASC])->limit($limit)->offset($offset);

        if ($search !== null && $search !== '') {
            $query->andWhere(['or', ['like', 'name', $search], ['like', 'nip', $search]]);
        }

        if ($status !== null && $status !== '') {
            $query->andWhere(['status' => $status]);
        }

        return array_map(Client::fromRow(...), $query->all());
    }

    public function count(?string $search = null, ?string $status = null): int
    {
        $query = $this->scopedQuery();

        if ($search !== null && $search !== '') {
            $query->andWhere(['or', ['like', 'name', $search], ['like', 'nip', $search]]);
        }

        if ($status !== null && $status !== '') {
            $query->andWhere(['status' => $status]);
        }

        return (int) $query->count();
    }

    public function findById(int $id): ?Client
    {
        $row = $this->scopedQuery()->andWhere(['id' => $id])->one();

        return $row === null ? null : Client::fromRow($row);
    }

    public function existsWithNip(string $nip, ?int $excludeId = null): bool
    {
        $query = $this->scopedQuery()->andWhere(['nip' => $nip]);

        if ($excludeId !== null) {
            $query->andWhere(['<>', 'id', $excludeId]);
        }

        return $query->exists();
    }

    /**
     * @return int Identyfikator nowo utworzonego klienta.
     */
    public function insert(Client $client): int
    {
        $this->db->createCommand()->insert(self::TABLE, [
            'tenant_id' => $this->tenantId(),
            'name' => $client->name,
            'nip' => $client->nip,
            'email' => $client->email,
            'phone' => $client->phone,
            'address' => $client->address,
            'status' => $client->status->value,
            'notes' => $client->notes,
            'created_at' => $client->createdAt->format('Y-m-d H:i:s.u'),
            'updated_at' => $client->updatedAt->format('Y-m-d H:i:s.u'),
        ])->execute();

        return (int) $this->db->getLastInsertId();
    }

    public function update(Client $client): void
    {
        $this->db->createCommand()->update(
            self::TABLE,
            [
                'name' => $client->name,
                'nip' => $client->nip,
                'email' => $client->email,
                'phone' => $client->phone,
                'address' => $client->address,
                'status' => $client->status->value,
                'notes' => $client->notes,
                'updated_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s.u'),
            ],
            // Warunek zawiera tenant_id - probA edycji cudzego klienta nie
            // zaktualizuje zadnego wiersza.
            $this->tenantCondition((int) $client->id),
        )->execute();
    }

    public function delete(int $id): void
    {
        $this->db->createCommand()->delete(self::TABLE, $this->tenantCondition($id))->execute();
    }
}
