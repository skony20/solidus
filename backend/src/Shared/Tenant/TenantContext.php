<?php

declare(strict_types=1);

namespace App\Shared\Tenant;

use RuntimeException;

/**
 * Przechowuje tenanta (biuro rachunkowe) obslugiwanego w biezacym zadaniu.
 *
 * Uzupelnia go {@see TenantMiddleware} na podstawie claimu `tid` z tokenu JWT.
 * Repozytoria czytaja stad identyfikator, zeby nigdy nie pokazac danych
 * jednego biura drugiemu.
 */
final class TenantContext
{
    private ?int $tenantId = null;
    private ?int $userId = null;

    /** @var string[] */
    private array $roles = [];

    /**
     * @param string[] $roles
     */
    public function set(int $tenantId, ?int $userId = null, array $roles = []): void
    {
        $this->tenantId = $tenantId;
        $this->userId = $userId;
        $this->roles = $roles;
    }

    public function clear(): void
    {
        $this->tenantId = null;
        $this->userId = null;
        $this->roles = [];
    }

    public function hasTenant(): bool
    {
        return $this->tenantId !== null;
    }

    /**
     * @throws RuntimeException gdy kod domenowy probuje siegnac po dane bez ustalonego tenanta.
     */
    public function tenantId(): int
    {
        if ($this->tenantId === null) {
            throw new RuntimeException(
                'Brak tenanta w kontekscie zadania. Endpoint musi byc chroniony przez TenantMiddleware.',
            );
        }

        return $this->tenantId;
    }

    public function userId(): ?int
    {
        return $this->userId;
    }

    /**
     * @return string[]
     */
    public function roles(): array
    {
        return $this->roles;
    }
}
