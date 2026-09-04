<?php

declare(strict_types=1);

namespace App\Shared\Tenant;

/**
 * Trait dla encji nalezacych do tenanta.
 *
 * Encja domenowa w Solidusie zawsze wie, do ktorego biura nalezy - bez tego
 * nie da sie jej zapisac ani zaudytowac.
 */
trait HasTenant
{
    public function tenantId(): int
    {
        return $this->tenantId;
    }

    public function belongsTo(int $tenantId): bool
    {
        return $this->tenantId === $tenantId;
    }
}
