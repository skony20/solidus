<?php

declare(strict_types=1);

namespace App\Shared\Auth;

/**
 * Wynik logowania: krotki token dostepowy + dlugi token odswiezajacy.
 *
 * Kontroler webowy chowa refreshToken w ciasteczku httpOnly, a przyszly klient
 * mobilny odda go w ciele odpowiedzi - stad oba sa tu obok siebie.
 */
final readonly class TokenPair
{
    public function __construct(
        public string $accessToken,
        public int $accessExpiresIn,
        public string $refreshToken,
        public int $refreshExpiresIn,
    ) {}
}
