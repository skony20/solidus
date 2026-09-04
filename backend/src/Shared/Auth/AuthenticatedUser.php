<?php

declare(strict_types=1);

namespace App\Shared\Auth;

/**
 * Tozsamosc odczytana z tokenu - to, co API wie o dzwoniacym.
 */
final readonly class AuthenticatedUser
{
    /**
     * @param string[] $roles
     */
    public function __construct(
        public int $userId,
        public int $tenantId,
        public array $roles,
        public string $tokenId,
    ) {}
}
