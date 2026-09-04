<?php

declare(strict_types=1);

namespace App\Module\Client\Entity;

use App\Shared\Tenant\HasTenant;
use DateTimeImmutable;

/**
 * Klient biura rachunkowego - firma, ktorej ksiegi prowadzi tenant.
 *
 * Zwykly obiekt PHP, bez dziedziczenia po warstwie bazodanowej (Yii3 nie ma
 * ActiveRecord w rdzeniu). Mapowanie z wiersza i na wiersz robi repozytorium.
 */
final class Client
{
    use HasTenant;

    public function __construct(
        public ?int $id,
        private int $tenantId,
        public string $name,
        public ?string $nip,
        public ?string $email,
        public ?string $phone,
        public ?string $address,
        public ClientStatus $status,
        public ?string $notes,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {}

    /**
     * @param array<string, mixed> $row Wiersz z tabeli `clients`.
     */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            tenantId: (int) $row['tenant_id'],
            name: (string) $row['name'],
            nip: $row['nip'] === null ? null : (string) $row['nip'],
            email: $row['email'] === null ? null : (string) $row['email'],
            phone: $row['phone'] === null ? null : (string) $row['phone'],
            address: $row['address'] === null ? null : (string) $row['address'],
            status: ClientStatus::from((string) $row['status']),
            notes: $row['notes'] === null ? null : (string) $row['notes'],
            createdAt: new DateTimeImmutable((string) $row['created_at']),
            updatedAt: new DateTimeImmutable((string) $row['updated_at']),
        );
    }

    /**
     * Reprezentacja wysylana do SPA.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'nip' => $this->nip,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'status' => $this->status->value,
            'statusLabel' => $this->status->label(),
            'notes' => $this->notes,
            'createdAt' => $this->createdAt->format(DATE_ATOM),
            'updatedAt' => $this->updatedAt->format(DATE_ATOM),
        ];
    }

    /**
     * Pola sledzone w audit logu - bez znacznikow czasu, ktore zmieniaja sie zawsze.
     *
     * @return array<string, mixed>
     */
    public function auditableAttributes(): array
    {
        return [
            'name' => $this->name,
            'nip' => $this->nip,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'status' => $this->status->value,
            'notes' => $this->notes,
        ];
    }
}
