<?php

declare(strict_types=1);

namespace App\Shared\Tenant;

use Yiisoft\Db\Query\Query;

/**
 * Trait dla repozytoriow tabel domenowych.
 *
 * Kazde zapytanie startuje z {@see self::scopedQuery()}, ktore od razu dokleja
 * warunek `tenant_id = :biezacy`. Dzieki temu izolacja danych jest domyslna,
 * a nie czyms, o czym trzeba pamietac przy kazdym nowym zapytaniu.
 *
 * Klasa uzywajaca traitu musi udostepnic wlasciwosci $db i $tenantContext
 * oraz stala TABLE z nazwa tabeli.
 */
trait TenantScoped
{
    protected function scopedQuery(): Query
    {
        return (new Query($this->db))
            ->from(static::TABLE)
            ->where(['tenant_id' => $this->tenantContext->tenantId()]);
    }

    protected function tenantId(): int
    {
        return $this->tenantContext->tenantId();
    }

    /**
     * Warunek do uzycia w UPDATE/DELETE, gdzie nie przechodzimy przez Query.
     *
     * @return array<string, int>
     */
    protected function tenantCondition(int $id): array
    {
        return ['id' => $id, 'tenant_id' => $this->tenantContext->tenantId()];
    }
}
