<?php

declare(strict_types=1);

namespace App\Module\Client\Migration;

use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Migration\RevertibleMigrationInterface;
use Yiisoft\Db\Migration\TransactionalMigrationInterface;

/**
 * Tabela `clients` - firmy obslugiwane przez biuro rachunkowe.
 *
 * Wzorzec dla kazdej kolejnej tabeli domenowej: `tenant_id` NOT NULL,
 * klucz obcy do `tenants`, indeks (tenant_id, id) pod filtrowanie repozytoriow.
 */
final class M250904100300CreateClients implements RevertibleMigrationInterface, TransactionalMigrationInterface
{
    private const OPTIONS = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci';

    public function up(MigrationBuilder $b): void
    {
        $b->createTable('clients', [
            'id' => 'BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY',
            'tenant_id' => 'BIGINT NOT NULL',
            'name' => 'VARCHAR(255) NOT NULL',
            // NIP przechowujemy w postaci znormalizowanej (same cyfry).
            'nip' => 'VARCHAR(10) NULL',
            'email' => 'VARCHAR(255) NULL',
            'phone' => 'VARCHAR(30) NULL',
            'address' => 'VARCHAR(255) NULL',
            // Etap onboardingu ze steppera w UI.
            'status' => "VARCHAR(30) NOT NULL DEFAULT 'lead'",
            'notes' => 'TEXT NULL',
            'created_at' => 'DATETIME(6) NOT NULL',
            'updated_at' => 'DATETIME(6) NOT NULL',
        ], self::OPTIONS);

        $b->createIndex('clients', 'ix_clients_tenant_id', ['tenant_id', 'id']);
        // NIP jest unikalny w obrebie biura - dwa biura moga obslugiwac ta sama firme.
        $b->createIndex('clients', 'ux_clients_tenant_nip', ['tenant_id', 'nip'], 'UNIQUE');
        $b->createIndex('clients', 'ix_clients_tenant_status', ['tenant_id', 'status']);
        $b->addForeignKey('clients', 'fk_clients_tenant', 'tenant_id', 'tenants', 'id', 'CASCADE', 'CASCADE');
    }

    public function down(MigrationBuilder $b): void
    {
        $b->dropTable('clients');
    }
}
