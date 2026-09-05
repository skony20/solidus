<?php

declare(strict_types=1);

namespace App\Module\Platform\Migration;

use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Migration\RevertibleMigrationInterface;
use Yiisoft\Db\Migration\TransactionalMigrationInterface;

/**
 * Historia platnosci abonamentowych biur - widziana i wpisywana wylacznie
 * przez panel operatora (Module\Platform), nigdy przez samo biuro.
 *
 * Kolacja jawnie `utf8mb4_unicode_ci`, nie `utf8mb4_0900_ai_ci`: ta druga
 * istnieje wylacznie w MySQL 8, a serwer produkcyjny (Cyber-Folks) stoi na
 * MariaDB - patrz docs/ARCHITECTURE.md sekcja 2.10 i docs/DEPLOY.md.
 */
final class M250906090100CreateTenantPayments implements RevertibleMigrationInterface, TransactionalMigrationInterface
{
    private const OPTIONS = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

    public function up(MigrationBuilder $b): void
    {
        $b->createTable('tenant_payments', [
            'id' => 'BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY',
            'tenant_id' => 'BIGINT NOT NULL',
            // Grosze, jak w pricing_plans - kwota pieniezna w float predzej
            // czy pozniej wyswietli sie jako 349.99999997.
            'amount' => 'BIGINT NOT NULL',
            'currency' => "CHAR(3) NOT NULL DEFAULT 'PLN'",
            'period_start' => 'DATE NOT NULL',
            'period_end' => 'DATE NOT NULL',
            'status' => "VARCHAR(20) NOT NULL DEFAULT 'paid'",
            // 'manual' dzis prawie zawsze - operator wpisuje przelew recznie.
            'provider' => "VARCHAR(30) NOT NULL DEFAULT 'manual'",
            // Identyfikator transakcji u zewnetrznego operatora platnosci.
            // Puste dla wpisow recznych - nie ma czego tu wpisac.
            'provider_reference' => 'VARCHAR(120) NULL',
            'note' => 'VARCHAR(255) NULL',
            'recorded_by_user_id' => 'BIGINT NULL',
            'created_at' => 'DATETIME(6) NOT NULL',
        ], self::OPTIONS);

        // Historia platnosci JEDNEGO biura, od najnowszej - dokladnie ten
        // dostep, ktorego potrzebuje ekran szczegolow biura.
        $b->createIndex('tenant_payments', 'ix_tenant_payments_tenant', ['tenant_id', 'period_start']);

        $b->addForeignKey(
            'tenant_payments',
            'fk_tenant_payments_tenant',
            'tenant_id',
            'tenants',
            'id',
            'CASCADE',
            'CASCADE',
        );

        // SET NULL, nie CASCADE: usuniecie (albo dezaktywacja) konta
        // administratora, ktory kiedys odnotowal platnosc, nie ma prawa
        // zabrac ze soba historii rozliczen biura.
        $b->addForeignKey(
            'tenant_payments',
            'fk_tenant_payments_recorded_by',
            'recorded_by_user_id',
            'users',
            'id',
            'SET NULL',
            'CASCADE',
        );
    }

    public function down(MigrationBuilder $b): void
    {
        $b->dropTable('tenant_payments');
    }
}
