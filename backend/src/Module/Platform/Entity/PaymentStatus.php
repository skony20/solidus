<?php

declare(strict_types=1);

namespace App\Module\Platform\Entity;

/**
 * Stan pojedynczej platnosci abonamentowej biura.
 */
enum PaymentStatus: string
{
    case Paid = 'paid';
    case Pending = 'pending';
    case Failed = 'failed';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Paid => 'Opłacona',
            self::Pending => 'Oczekuje',
            self::Failed => 'Nieudana',
            self::Refunded => 'Zwrócona',
        };
    }
}
