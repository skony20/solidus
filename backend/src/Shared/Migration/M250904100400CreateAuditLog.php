<?php

declare(strict_types=1);

namespace App\Shared\Migration;

use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Migration\RevertibleMigrationInterface;
use Yiisoft\Db\Migration\TransactionalMigrationInterface;

/**
 * Tabela `audit_log` - dziennik zmian wszystkich encji domenowych.
 *
 * Wymog AML i RODO: musimy umiec odtworzyc, kto i kiedy zmienil dane.
 * Kolumna `changes` jest typu JSON, bo ksztalt zmian jest inny dla kazdej
 * encji. Gdyby w przyszlosci trzeba bylo filtrowac po konkretnym polu
 * wewnatrz JSON-a, dokladamy generated column + indeks - teraz nie jest
 * to potrzebne i tylko spowolniloby zapisy.
 *
 * Brak klucza obcego do encji jest celowy: wpis w dzienniku musi przetrwac
 * usuniecie encji, ktorej dotyczy.
 */
final class M250904100400CreateAuditLog implements RevertibleMigrationInterface, TransactionalMigrationInterface
{
    private const OPTIONS = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

    public function up(MigrationBuilder $b): void
    {
        $b->createTable('audit_log', [
            'id' => 'BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY',
            'tenant_id' => 'BIGINT NOT NULL',
            // NULL, gdy zmiane wykonal proces w tle, a nie czlowiek.
            'user_id' => 'BIGINT NULL',
            'entity_type' => 'VARCHAR(100) NOT NULL',
            'entity_id' => 'BIGINT NOT NULL',
            'action' => "ENUM('create','update','delete') NOT NULL",
            'changes' => 'JSON NOT NULL',
            // 45 znakow - dlugosc adresu IPv6 w zapisie tekstowym.
            'ip' => 'VARCHAR(45) NULL',
            // Mikrosekundy zachowuja kolejnosc zmian wykonanych w tej samej sekundzie.
            'created_at' => 'DATETIME(6) NOT NULL',
        ], self::OPTIONS);

        $b->createIndex('audit_log', 'ix_audit_log_entity', ['tenant_id', 'entity_type', 'entity_id']);
        $b->createIndex('audit_log', 'ix_audit_log_created', ['tenant_id', 'created_at']);
        $b->addForeignKey('audit_log', 'fk_audit_log_tenant', 'tenant_id', 'tenants', 'id', 'CASCADE', 'CASCADE');
    }

    public function down(MigrationBuilder $b): void
    {
        $b->dropTable('audit_log');
    }
}
