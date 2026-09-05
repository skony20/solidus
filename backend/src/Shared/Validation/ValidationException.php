<?php

declare(strict_types=1);

namespace App\Shared\Validation;

use RuntimeException;

/**
 * Niesie komplet bledow walidacji, zeby formularz w SPA mogl podswietlic
 * wszystkie zle pola naraz, a nie jedno po drugim.
 */
final class ValidationException extends RuntimeException
{
    /**
     * @param array<string, string[]> $errors
     */
    public function __construct(private readonly array $errors)
    {
        parent::__construct('Dane sa niepoprawne.');
    }

    /**
     * @return array<string, string[]>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
