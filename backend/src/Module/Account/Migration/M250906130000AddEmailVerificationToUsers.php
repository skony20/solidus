<?php

declare(strict_types=1);

namespace App\Module\Account\Migration;

use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Migration\RevertibleMigrationInterface;
use Yiisoft\Db\Migration\TransactionalMigrationInterface;

/**
 * Potwierdzanie adresu e-mail przy rejestracji nowego biura.
 *
 * Konto powstaje niezweryfikowane (`email_verified_at IS NULL`) i nie da sie
 * na nie zalogowac, dopoki wlasciciel nie poda kodu wyslanego na podany adres.
 * Kod jest 6-cyfrowy, przechowywany jako hash (nigdy jawnie), z terminem
 * waznosci i licznikiem bledych prob - po jego przekroczeniu trzeba poprosic
 * o nowy kod.
 *
 * `email_verified_at` domyslnie NULL na poziomie KOLUMNY, ale istniejace konta
 * (zalozone przed ta migracja poleceniem `curl`) dostaja `NOW()` w tej samej
 * migracji - inaczej deweloperzy zostaliby z kontem, na ktore nie da sie
 * wejsc. Kolumna `is_active` zostaje nietknieta: to osobna bramka, pod
 * przyszle wylaczanie kont przez operatora.
 */
final class M250906130000AddEmailVerificationToUsers implements RevertibleMigrationInterface, TransactionalMigrationInterface
{
    public function up(MigrationBuilder $b): void
    {
        $b->addColumn('users', 'email_verified_at', 'DATETIME(6) NULL AFTER is_active');
        $b->addColumn('users', 'verification_code_hash', 'VARCHAR(255) NULL AFTER email_verified_at');
        $b->addColumn('users', 'verification_code_expires_at', 'DATETIME(6) NULL AFTER verification_code_hash');
        $b->addColumn('users', 'verification_code_sent_at', 'DATETIME(6) NULL AFTER verification_code_expires_at');
        $b->addColumn('users', 'verification_attempts', 'SMALLINT NOT NULL DEFAULT 0 AFTER verification_code_sent_at');

        // Konta zalozone przed wprowadzeniem weryfikacji dzialaja dalej.
        $b->execute('UPDATE users SET email_verified_at = created_at WHERE email_verified_at IS NULL');
    }

    public function down(MigrationBuilder $b): void
    {
        $b->dropColumn('users', 'verification_attempts');
        $b->dropColumn('users', 'verification_code_sent_at');
        $b->dropColumn('users', 'verification_code_expires_at');
        $b->dropColumn('users', 'verification_code_hash');
        $b->dropColumn('users', 'email_verified_at');
    }
}
