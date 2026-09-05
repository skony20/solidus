<?php

declare(strict_types=1);

namespace App\Shared\Migration;

use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Migration\RevertibleMigrationInterface;
use Yiisoft\Db\Migration\TransactionalMigrationInterface;

/**
 * Tabela `tenants` - jedno biuro rachunkowe = jeden wiersz.
 *
 * To korzen calej izolacji danych w Solidusie: kazda tabela domenowa
 * wskazuje tu kluczem obcym.
 */
final class M250904100000CreateTenants implements RevertibleMigrationInterface, TransactionalMigrationInterface
{
    private const OPTIONS = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

    public function up(MigrationBuilder $b): void
    {
        $b->createTable('tenants', [
            'id' => 'BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY',
            'name' => 'VARCHAR(255) NOT NULL',
            // Slug trafia do adresow i subdomen, wiec musi byc unikalny globalnie.
            'slug' => 'VARCHAR(100) NOT NULL',
            'plan' => "VARCHAR(50) NOT NULL DEFAULT 'starter'",
            'created_at' => 'DATETIME(6) NOT NULL',
        ], self::OPTIONS);

        $b->createIndex('tenants', 'ux_tenants_slug', 'slug', 'UNIQUE');
    }

    public function down(MigrationBuilder $b): void
    {
        $b->dropTable('tenants');
    }
}
