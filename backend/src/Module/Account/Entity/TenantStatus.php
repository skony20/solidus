<?php

declare(strict_types=1);

namespace App\Module\Account\Entity;

/**
 * Stan konta biura z punktu widzenia operatora Solidusa.
 *
 * Nie mylic z {@see \App\Module\Client\Entity\ClientStatus} - tamten opisuje
 * etap onboardingu KLIENTA biura rachunkowego, ten opisuje stan SAMEGO BIURA
 * jako abonenta Solidusa. Dwie zupelnie rozne osie.
 */
enum TenantStatus: string
{
    /** Okres próbny - dostęp działa, jeszcze bez płatności. */
    case Trial = 'trial';

    /** Abonament opłacony, pełny dostęp. */
    case Active = 'active';

    /**
     * Zawieszony przez operatora - logowanie odrzucone na `/api/auth/login`
     * i `/api/auth/refresh`. Access token wydany przed zawieszeniem żyje
     * maksymalnie 15 minut (patrz JwtService) - to samo okno, w którym
     * dziala juz odbieranie rol, wiec zawieszenie nie wprowadza nowego
     * wzorca w aplikacji.
     */
    case Suspended = 'suspended';

    /** Biuro zrezygnowało lub umowa wygasła. Dane zostają, dostęp nie. */
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Trial => 'Okres próbny',
            self::Active => 'Aktywne',
            self::Suspended => 'Zawieszone',
            self::Cancelled => 'Zakończone',
        };
    }

    /** Czy w tym stanie biuro może się zalogować. */
    public function allowsLogin(): bool
    {
        return match ($this) {
            self::Trial, self::Active => true,
            self::Suspended, self::Cancelled => false,
        };
    }
}
