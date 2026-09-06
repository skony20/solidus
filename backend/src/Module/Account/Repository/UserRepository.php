<?php

declare(strict_types=1);

namespace App\Module\Account\Repository;

use App\Module\Account\Entity\User;
use DateTimeImmutable;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Query\Query;

/**
 * Dostep do tabeli `users`.
 *
 * Logowanie odbywa sie zanim istnieje kontekst tenanta, dlatego to
 * repozytorium przyjmuje tenant_id jawnie w argumentach zamiast czytac go
 * z {@see \App\Shared\Tenant\TenantContext}. Wszystkie zapytania i tak
 * zawsze zawieraja warunek po tenancie.
 */
final readonly class UserRepository
{
    public const TABLE = 'users';

    public function __construct(
        private ConnectionInterface $db,
    ) {}

    public function findById(int $id): ?User
    {
        $row = (new Query($this->db))->from(self::TABLE)->where(['id' => $id])->one();

        return $row === null ? null : User::fromRow($row);
    }

    public function findByEmail(int $tenantId, string $email): ?User
    {
        $row = (new Query($this->db))
            ->from(self::TABLE)
            ->where(['tenant_id' => $tenantId, 'email' => mb_strtolower($email)])
            ->one();

        return $row === null ? null : User::fromRow($row);
    }

    /**
     * Wyszukanie po slugu biura zamiast po tenant_id - na potrzeby konsoli,
     * gdzie czlowiek zna nazwe biura, a nie jego identyfikator liczbowy,
     * oraz publicznego potwierdzania e-maila (klient zna tylko slug).
     */
    public function findByEmailAndTenantSlug(string $tenantSlug, string $email): ?User
    {
        $row = (new Query($this->db))
            ->select('u.*')
            ->from(['u' => self::TABLE])
            ->innerJoin(['t' => 'tenants'], 'u.tenant_id = t.id')
            ->where(['t.slug' => $tenantSlug, 'u.email' => mb_strtolower($email)])
            ->one();

        return $row === null ? null : User::fromRow($row);
    }

    /**
     * Podmienia caly zestaw rol uzytkownika.
     *
     * Wolane wylacznie z konsoli (patrz {@see \App\Console\GrantPlatformAdminCommand}).
     * Celowo nie ma tu metody "dodaj role" przyjmujacej pojedyncza role -
     * takie API kusi, zeby wywolac je z kontrolera.
     *
     * @param string[] $roles
     */
    public function replaceRoles(int $userId, array $roles): void
    {
        $this->db->createCommand()->update(
            self::TABLE,
            [
                'roles' => json_encode(array_values(array_unique($roles)), JSON_THROW_ON_ERROR),
                'updated_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s.u'),
            ],
            ['id' => $userId],
        )->execute();
    }

    public function emailExists(int $tenantId, string $email): bool
    {
        return (new Query($this->db))
            ->from(self::TABLE)
            ->where(['tenant_id' => $tenantId, 'email' => mb_strtolower($email)])
            ->exists();
    }

    /**
     * @param string[] $roles
     * @param bool $emailVerified false przy rejestracji przez formularz - konto
     *        czeka wtedy na potwierdzenie adresu. true dla kont zakladanych
     *        z konsoli i w testach, ktore maja od razu dzialac.
     */
    public function create(
        int $tenantId,
        string $email,
        string $plainPassword,
        string $name,
        array $roles = ['owner'],
        bool $emailVerified = true,
    ): User {
        $now = new DateTimeImmutable();
        $hash = password_hash($plainPassword, PASSWORD_DEFAULT);
        $verifiedAt = $emailVerified ? $now : null;

        $this->db->createCommand()->insert(self::TABLE, [
            'tenant_id' => $tenantId,
            'email' => mb_strtolower($email),
            'password_hash' => $hash,
            'name' => $name,
            'roles' => json_encode(array_values($roles), JSON_THROW_ON_ERROR),
            'is_active' => 1,
            'email_verified_at' => $verifiedAt?->format('Y-m-d H:i:s.u'),
            'created_at' => $now->format('Y-m-d H:i:s.u'),
            'updated_at' => $now->format('Y-m-d H:i:s.u'),
        ])->execute();

        return new User(
            id: (int) $this->db->getLastInsertId(),
            tenantId: $tenantId,
            email: mb_strtolower($email),
            passwordHash: $hash,
            name: $name,
            roles: array_values($roles),
            isActive: true,
            createdAt: $now,
            updatedAt: $now,
            emailVerifiedAt: $verifiedAt,
        );
    }

    /**
     * Zapisuje swiezy kod weryfikacyjny (hash) i zeruje licznik bledych prob.
     */
    public function storeVerificationCode(
        int $userId,
        string $codeHash,
        DateTimeImmutable $expiresAt,
        DateTimeImmutable $sentAt,
    ): void {
        $this->db->createCommand()->update(
            self::TABLE,
            [
                'verification_code_hash' => $codeHash,
                'verification_code_expires_at' => $expiresAt->format('Y-m-d H:i:s.u'),
                'verification_code_sent_at' => $sentAt->format('Y-m-d H:i:s.u'),
                'verification_attempts' => 0,
                'updated_at' => $sentAt->format('Y-m-d H:i:s.u'),
            ],
            ['id' => $userId],
        )->execute();
    }

    /**
     * Stan kodu weryfikacyjnego. Trzymany osobno od encji {@see User}, bo to
     * szczegol jednej sciezki (rejestracja), a nie cecha uzytkownika.
     *
     * @return array{hash: ?string, expiresAt: ?DateTimeImmutable, sentAt: ?DateTimeImmutable, attempts: int}
     */
    public function verificationState(int $userId): array
    {
        $row = (new Query($this->db))
            ->select([
                'verification_code_hash',
                'verification_code_expires_at',
                'verification_code_sent_at',
                'verification_attempts',
            ])
            ->from(self::TABLE)
            ->where(['id' => $userId])
            ->one();

        if (!is_array($row)) {
            return ['hash' => null, 'expiresAt' => null, 'sentAt' => null, 'attempts' => 0];
        }

        $toDate = static fn(mixed $v): ?DateTimeImmutable => $v === null || $v === ''
            ? null
            : new DateTimeImmutable((string) $v);

        $hash = $row['verification_code_hash'];

        return [
            'hash' => $hash === null || $hash === '' ? null : (string) $hash,
            'expiresAt' => $toDate($row['verification_code_expires_at']),
            'sentAt' => $toDate($row['verification_code_sent_at']),
            'attempts' => (int) $row['verification_attempts'],
        ];
    }

    public function incrementVerificationAttempts(int $userId): void
    {
        $this->db->createCommand(
            'UPDATE ' . $this->db->getQuoter()->quoteTableName(self::TABLE)
            . ' SET verification_attempts = verification_attempts + 1 WHERE id = :id',
            ['id' => $userId],
        )->execute();
    }

    /**
     * Oznacza adres jako potwierdzony i kasuje juz niepotrzebny kod.
     */
    public function markEmailVerified(int $userId, DateTimeImmutable $at): void
    {
        $this->db->createCommand()->update(
            self::TABLE,
            [
                'email_verified_at' => $at->format('Y-m-d H:i:s.u'),
                'verification_code_hash' => null,
                'verification_code_expires_at' => null,
                'verification_code_sent_at' => null,
                'verification_attempts' => 0,
                'updated_at' => $at->format('Y-m-d H:i:s.u'),
            ],
            ['id' => $userId],
        )->execute();
    }
}
