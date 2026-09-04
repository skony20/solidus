<?php

declare(strict_types=1);

namespace App\Module\Client\Service;

use App\Module\Client\Dto\ClientInput;
use App\Module\Client\Dto\ValidationException;
use App\Module\Client\Entity\Client;
use App\Module\Client\Repository\ClientRepository;
use App\Shared\Audit\AuditLogger;
use App\Shared\Tenant\TenantContext;
use DateTimeImmutable;

/**
 * Operacje na kliencie biura rachunkowego.
 *
 * Serwis, a nie kontroler, jest wlascicielem regul: sprawdza unikalnosc NIP-u
 * i - co wazniejsze - dopisuje kazda zmiane do audit logu. Gdyby zapis do
 * dziennika siedzial w kontrolerze, latwo byloby go pominac przy dodawaniu
 * kolejnego wejscia (import, kolejka, komenda konsolowa).
 */
final readonly class ClientService
{
    public const ENTITY_TYPE = 'client';

    public function __construct(
        private ClientRepository $repository,
        private AuditLogger $auditLogger,
        private TenantContext $tenantContext,
    ) {}

    public function create(ClientInput $input, ?string $ip = null): Client
    {
        $this->assertNipIsFree($input->nip);

        $now = new DateTimeImmutable();
        $client = new Client(
            id: null,
            tenantId: $this->tenantContext->tenantId(),
            name: $input->name,
            nip: $input->nip,
            email: $input->email,
            phone: $input->phone,
            address: $input->address,
            status: $input->status,
            notes: $input->notes,
            createdAt: $now,
            updatedAt: $now,
        );

        $client->id = $this->repository->insert($client);

        $this->auditLogger->created(self::ENTITY_TYPE, $client->id, $client->auditableAttributes(), $ip);

        return $client;
    }

    public function update(int $id, ClientInput $input, ?string $ip = null): Client
    {
        $client = $this->repository->findById($id);

        if ($client === null) {
            throw new ClientNotFoundException("Klient o identyfikatorze {$id} nie istnieje.");
        }

        $this->assertNipIsFree($input->nip, $id);

        $before = $client->auditableAttributes();

        $client->name = $input->name;
        $client->nip = $input->nip;
        $client->email = $input->email;
        $client->phone = $input->phone;
        $client->address = $input->address;
        $client->status = $input->status;
        $client->notes = $input->notes;
        $client->updatedAt = new DateTimeImmutable();

        $this->repository->update($client);

        $this->auditLogger->updated(self::ENTITY_TYPE, $id, $before, $client->auditableAttributes(), $ip);

        return $client;
    }

    public function delete(int $id, ?string $ip = null): void
    {
        $client = $this->repository->findById($id);

        if ($client === null) {
            throw new ClientNotFoundException("Klient o identyfikatorze {$id} nie istnieje.");
        }

        // Dziennik zapisujemy przed usunieciem, zeby zachowac stan encji.
        $this->auditLogger->deleted(self::ENTITY_TYPE, $id, $client->auditableAttributes(), $ip);

        $this->repository->delete($id);
    }

    /**
     * @throws ValidationException
     */
    private function assertNipIsFree(?string $nip, ?int $excludeId = null): void
    {
        if ($nip === null) {
            return;
        }

        if ($this->repository->existsWithNip($nip, $excludeId)) {
            throw new ValidationException(['nip' => ['Klient o tym numerze NIP juz istnieje w tym biurze.']]);
        }
    }
}
