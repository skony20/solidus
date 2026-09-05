<?php

declare(strict_types=1);

namespace App\Module\Account\Migration;

use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Migration\RevertibleMigrationInterface;
use Yiisoft\Db\Migration\TransactionalMigrationInterface;

/**
 * Rozszerza `tenants` o pola potrzebne panelowi operatora Solidusa
 * (Module\Platform): stan konta i powiazanie z prawdziwym cennikiem.
 *
 * `status` domyslnie 'active' na poziomie KOLUMNY (a nie tylko w kodzie PHP) -
 * biura zalozone przed ta migracja maja dzialac dalej bez recznej interwencji.
 * Nowe biura ustawia na 'trial' `TenantRepository::create()`.
 *
 * `pricing_plan_id` jest NULL-owalne i ma `ON DELETE SET NULL`: skasowanie
 * planu z cennika (Module\Pricing) nie ma prawa zabrac ze soba biur, ktore
 * do niego naleza - traca tylko powiazanie z KATALOGIEM, nie dostep do
 * systemu. Kolumna `plan` (tekstowa, istniejaca od poczatku) zostaje jako
 * nazwa wyswietlana - `TenantAdminService::assignPlan()` aktualizuje obie
 * naraz, zeby sie nie rozjechaly.
 */
final class M250906090000AddTenantBillingFields implements RevertibleMigrationInterface, TransactionalMigrationInterface
{
    public function up(MigrationBuilder $b): void
    {
        $b->addColumn('tenants', 'status', "VARCHAR(20) NOT NULL DEFAULT 'active' AFTER plan");
        $b->addColumn('tenants', 'pricing_plan_id', 'BIGINT NULL AFTER status');

        $b->createIndex('tenants', 'ix_tenants_status', ['status']);
        $b->addForeignKey(
            'tenants',
            'fk_tenants_pricing_plan',
            'pricing_plan_id',
            'pricing_plans',
            'id',
            'SET NULL',
            'CASCADE',
        );
    }

    public function down(MigrationBuilder $b): void
    {
        $b->dropForeignKey('tenants', 'fk_tenants_pricing_plan');
        $b->dropIndex('tenants', 'ix_tenants_status');
        $b->dropColumn('tenants', 'pricing_plan_id');
        $b->dropColumn('tenants', 'status');
    }
}
