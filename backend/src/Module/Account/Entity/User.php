<?php

declare(strict_types=1);

namespace App\Module\Account\Entity;

use App\Shared\Tenant\HasTenant;
use DateTimeImmutable;

/**
 * Pracownik biura rachunkowego.
 */
final class User
{
    use HasTenant;

    /**
     * @param string[] $roles
     */
    public function __construct(
        public ?int $id,
        private int $tenantId,
        public string $email,
        public string $passwordHash,
        public string $name,
        public array $roles,
        public bool $isActive,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        public ?DateTimeImmutable $emailVerifiedAt = null,
    ) {}

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        // Kolumna `roles` jest typu JSON, więc jej zawartość jest dowolna -
        // przepuszczamy tylko napisy, zamiast ufać kształtowi danych w bazie.
        $roles = array_values(array_filter(
            (array) (json_decode((string) $row['roles'], true) ?: []),
            static fn(mixed $role): bool => is_string($role),
        ));

        $verifiedAt = $row['email_verified_at'] ?? null;

        return new self(
            id: (int) $row['id'],
            tenantId: (int) $row['tenant_id'],
            email: (string) $row['email'],
            passwordHash: (string) $row['password_hash'],
            name: (string) $row['name'],
            roles: $roles,
            isActive: (bool) $row['is_active'],
            createdAt: new DateTimeImmutable((string) $row['created_at']),
            updatedAt: new DateTimeImmutable((string) $row['updated_at']),
            emailVerifiedAt: $verifiedAt === null || $verifiedAt === ''
                ? null
                : new DateTimeImmutable((string) $verifiedAt),
        );
    }

    public function verifyPassword(string $plainPassword): bool
    {
        return password_verify($plainPassword, $this->passwordHash);
    }

    public function isEmailVerified(): bool
    {
        return $this->emailVerifiedAt !== null;
    }

    /**
     * Reprezentacja dla /auth/me. Hash hasla nigdy nie opuszcza serwera.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'name' => $this->name,
            'roles' => $this->roles,
            'isActive' => $this->isActive,
            'emailVerified' => $this->isEmailVerified(),
        ];
    }
}
