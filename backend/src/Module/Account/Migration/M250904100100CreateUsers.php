<?php

declare(strict_types=1);

namespace App\Module\Account\Migration;

use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Migration\RevertibleMigrationInterface;
use Yiisoft\Db\Migration\TransactionalMigrationInterface;

/**
 * Tabela `users` - pracownicy biura rachunkowego.
 *
 * E-mail jest unikalny w obrebie tenanta, nie globalnie: ta sama osoba moze
 * pracowac w dwoch biurach na tym samym adresie.
 */
final class M250904100100CreateUsers implements RevertibleMigrationInterface, TransactionalMigrationInterface
{
    private const OPTIONS = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

    public function up(MigrationBuilder $b): void
    {
        $b->createTable('users', [
            'id' => 'BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY',
            'tenant_id' => 'BIGINT NOT NULL',
            'email' => 'VARCHAR(255) NOT NULL',
            'password_hash' => 'VARCHAR(255) NOT NULL',
            'name' => 'VARCHAR(255) NOT NULL',
            // Role jako JSON: na tym etapie wystarczy prosta lista nazw.
            'roles' => 'JSON NOT NULL',
            'is_active' => 'TINYINT(1) NOT NULL DEFAULT 1',
            'created_at' => 'DATETIME(6) NOT NULL',
            'updated_at' => 'DATETIME(6) NOT NULL',
        ], self::OPTIONS);

        $b->createIndex('users', 'ux_users_tenant_email', ['tenant_id', 'email'], 'UNIQUE');
        $b->createIndex('users', 'ix_users_tenant_id', ['tenant_id', 'id']);
        $b->addForeignKey('users', 'fk_users_tenant', 'tenant_id', 'tenants', 'id', 'CASCADE', 'CASCADE');
    }

    public function down(MigrationBuilder $b): void
    {
        $b->dropTable('users');
    }
}
