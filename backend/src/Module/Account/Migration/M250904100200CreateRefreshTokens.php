<?php

declare(strict_types=1);

namespace App\Module\Account\Migration;

use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Migration\RevertibleMigrationInterface;
use Yiisoft\Db\Migration\TransactionalMigrationInterface;

/**
 * Tabela `refresh_tokens` - rejestr dlugozyjacych sesji.
 *
 * Trzymamy tu wylacznie identyfikator tokenu (jti), nigdy samego tokenu.
 * Dzieki temu da sie uniewaznic pojedyncza sesje i wylistowac urzadzenia,
 * mimo ze JWT sam w sobie jest bezstanowy.
 */
final class M250904100200CreateRefreshTokens implements RevertibleMigrationInterface, TransactionalMigrationInterface
{
    private const OPTIONS = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

    public function up(MigrationBuilder $b): void
    {
        $b->createTable('refresh_tokens', [
            'jti' => 'CHAR(32) NOT NULL PRIMARY KEY',
            'user_id' => 'BIGINT NOT NULL',
            'tenant_id' => 'BIGINT NOT NULL',
            'expires_at' => 'DATETIME NOT NULL',
            'revoked_at' => 'DATETIME NULL',
            'user_agent' => 'VARCHAR(255) NULL',
            'ip' => 'VARCHAR(45) NULL',
            'created_at' => 'DATETIME NOT NULL',
        ], self::OPTIONS);

        $b->createIndex('refresh_tokens', 'ix_refresh_tokens_user', ['user_id', 'revoked_at']);
        $b->createIndex('refresh_tokens', 'ix_refresh_tokens_tenant', ['tenant_id', 'expires_at']);
        $b->addForeignKey('refresh_tokens', 'fk_refresh_tokens_user', 'user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $b->addForeignKey('refresh_tokens', 'fk_refresh_tokens_tenant', 'tenant_id', 'tenants', 'id', 'CASCADE', 'CASCADE');
    }

    public function down(MigrationBuilder $b): void
    {
        $b->dropTable('refresh_tokens');
    }
}
