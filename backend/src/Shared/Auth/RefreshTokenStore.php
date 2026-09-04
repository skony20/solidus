<?php

declare(strict_types=1);

namespace App\Shared\Auth;

use DateTimeImmutable;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Query\Query;

/**
 * Rejestr wydanych tokenow odswiezajacych (tabela `refresh_tokens`).
 *
 * Sam token JWT jest bezstanowy, wiec bez tego rejestru nie dalo by sie
 * wylogowac zdalnie ani pokazac "aktywne urzadzenia". Trzymamy tu tylko
 * identyfikator tokenu (jti) - nigdy samego tokenu.
 */
final readonly class RefreshTokenStore
{
    private const TABLE = 'refresh_tokens';

    public function __construct(
        private ConnectionInterface $db,
    ) {}

    public function store(
        string $jti,
        int $userId,
        int $tenantId,
        DateTimeImmutable $expiresAt,
        ?string $userAgent = null,
        ?string $ip = null,
    ): void {
        $this->db->createCommand()->insert(self::TABLE, [
            'jti' => $jti,
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
            'revoked_at' => null,
            'user_agent' => $userAgent === null ? null : mb_substr($userAgent, 0, 255),
            'ip' => $ip,
            'created_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
        ])->execute();
    }

    public function isActive(string $jti): bool
    {
        $row = (new Query($this->db))
            ->from(self::TABLE)
            ->where(['jti' => $jti, 'revoked_at' => null])
            ->andWhere(['>', 'expires_at', (new DateTimeImmutable())->format('Y-m-d H:i:s')])
            ->one();

        return $row !== null;
    }

    public function revoke(string $jti): void
    {
        $this->db->createCommand()->update(
            self::TABLE,
            ['revoked_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s')],
            ['jti' => $jti, 'revoked_at' => null],
        )->execute();
    }

    /**
     * Uniewaznia wszystkie sesje uzytkownika - np. po zmianie hasla.
     */
    public function revokeAllForUser(int $userId): void
    {
        $this->db->createCommand()->update(
            self::TABLE,
            ['revoked_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s')],
            ['user_id' => $userId, 'revoked_at' => null],
        )->execute();
    }

    /**
     * Aktywne sesje uzytkownika - podklad pod ekran "moje urzadzenia".
     *
     * @return array<int, array<string, mixed>>
     */
    public function activeSessions(int $userId): array
    {
        return (new Query($this->db))
            ->select(['jti', 'user_agent', 'ip', 'created_at', 'expires_at'])
            ->from(self::TABLE)
            ->where(['user_id' => $userId, 'revoked_at' => null])
            ->andWhere(['>', 'expires_at', (new DateTimeImmutable())->format('Y-m-d H:i:s')])
            ->orderBy(['created_at' => SORT_DESC])
            ->all();
    }
}
