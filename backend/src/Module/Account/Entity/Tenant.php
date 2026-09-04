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
            createdAt: new DateTimeImmutable((string) $row['created_at']),
        );
    }

    /**
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
}
