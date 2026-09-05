<?php

declare(strict_types=1);

namespace App\Module\Platform\Service;

use RuntimeException;

final class TenantNotFoundException extends RuntimeException
{
    public function __construct(int $id)
    {
        parent::__construct(sprintf('Nie znaleziono biura o identyfikatorze %d.', $id));
    }
}
