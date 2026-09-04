<?php

declare(strict_types=1);

namespace App\Module\Client\Entity;

/**
 * Etapy onboardingu klienta - odpowiadaja krokom steppera w interfejsie.
 */
enum ClientStatus: string
{
    case Lead = 'lead';
    case Onboarding = 'onboarding';
    case Active = 'active';
    case Suspended = 'suspended';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Lead => 'Potencjalny',
            self::Onboarding => 'Wdrozenie',
            self::Active => 'Aktywny',
            self::Suspended => 'Zawieszony',
            self::Archived => 'Archiwum',
        };
    }
}
