<?php

declare(strict_types=1);

namespace App\Module\Pricing\Service;

use RuntimeException;

/**
 * Plan o podanym identyfikatorze nie istnieje.
 */
final class PricingPlanNotFoundException extends RuntimeException
{
    public function __construct(int $id)
    {
        parent::__construct(sprintf('Nie znaleziono planu cennika o identyfikatorze %d.', $id));
    }
}
