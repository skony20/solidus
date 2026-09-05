<?php

declare(strict_types=1);

namespace App\Module\Account\Entity;

use DateTimeImmutable;

/**
 * Tenant = jedno biuro rachunkowe korzystajace z Solidusa.
 */
final readonly class Tenant
{
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
        public string $plan,
        public TenantStatus $status,
        /** Powiazanie z prawdziwym cennikiem; null = biuro spoza katalogu (np. wycena indywidualna). */
        public ?int $pricingPlanId,
        public DateTimeImmutable $createdAt,
    ) {}

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            name: (string) $row['name'],
            slug: (string) $row['slug'],
            plan: (string) $row['plan'],
            // Domyslnie 'active' - wiersze sprzed tej kolumny (m.in. konto zalozone
            // przy rejestracji) nie maja nic ustawionego, a milczace zablokowanie
            // istniejacego biura bylby najgorszym mozliwym efektem migracji.
            status: TenantStatus::tryFrom((string) ($row['status'] ?? '')) ?? TenantStatus::Active,
            pricingPlanId: isset($row['pricing_plan_id']) ? (int) $row['pricing_plan_id'] : null,
            createdAt: new DateTimeImmutable((string) $row['created_at']),
        );
    }

    /**
     * Reprezentacja dla /auth/login i /auth/me - to, co widzi zalogowany
     * pracownik biura. Status i pricingPlanId to sprawa operatora, nie jego -
     * dlatego zostaja poza tym API i wystepuja wylacznie w {@see toAdminArray()}.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'plan' => $this->plan,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toAdminArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'plan' => $this->plan,
            'status' => $this->status->value,
            'statusLabel' => $this->status->label(),
            'pricingPlanId' => $this->pricingPlanId,
            'createdAt' => $this->createdAt->format(DATE_ATOM),
        ];
    }
}
