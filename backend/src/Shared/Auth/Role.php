<?php

declare(strict_types=1);

namespace App\Shared\Auth;

/**
 * Role uzytkownikow Solidusa.
 *
 * Role sa napisami w kolumnie JSON `users.roles`, ale kod ma sie odwolywac
 * do tych stalych, a nie do literalow - literal wpisany z bledem w warunku
 * uprawnien to cicha dziura, ktorej nie wykryje ani IDE, ani analiza statyczna.
 *
 * WAZNE ROZROZNIENIE:
 *  - `owner` i `member` dzialaja w obrebie jednego biura (tenanta),
 *  - `platform_admin` jest rola PONAD biurami: dotyczy danych wspolnych dla
 *    calego systemu (dzis: cennik na stronie informacyjnej). Nie daje ona
 *    dostepu do danych innych biur - izolacja tenantow zostaje nienaruszona,
 *    bo repozytoria domenowe i tak filtruja po `tenant_id` z tokenu.
 */
final class Role
{
    /** Wlasciciel biura - konto zalozone przy rejestracji tenanta. */
    public const OWNER = 'owner';

    /** Zwykly pracownik biura. */
    public const MEMBER = 'member';

    /**
     * Administrator calego systemu Solidus (my, nie klient).
     *
     * Nadawana wylacznie z konsoli - `php yii admin:grant` - zeby nie dalo sie
     * jej przyznac przez API, nawet przez pomylke w kodzie kontrolera.
     */
    public const PLATFORM_ADMIN = 'platform_admin';

    /**
     * @param string[] $roles
     */
    public static function isPlatformAdmin(array $roles): bool
    {
        return in_array(self::PLATFORM_ADMIN, $roles, true);
    }
}
