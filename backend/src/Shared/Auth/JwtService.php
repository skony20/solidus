<?php

declare(strict_types=1);

namespace App\Shared\Auth;

use DateTimeImmutable;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Throwable;

/**
 * Cala wiedza o wystawianiu i weryfikowaniu tokenow JWT.
 *
 * WAZNE: logika zyje tutaj, a nie w kontrolerze. Planowany klient mobilny
 * przejdzie ta sama sciezka (login -> issue, refresh -> rotate), a rozni sie
 * wylacznie tym, ze refresh token dostanie w ciele odpowiedzi zamiast
 * w ciasteczku httpOnly. Kontrolery maja byc cienkie.
 */
final readonly class JwtService
{
    public function __construct(
        private RefreshTokenStore $refreshTokenStore,
        private string $secret,
        private string $algorithm,
        private string $issuer,
        private int $accessTtl,
        private int $refreshTtl,
    ) {}

    /**
     * Wystawia swieza pare tokenow i zapisuje refresh token w bazie,
     * zeby dalo sie go pozniej uniewaznic i pokazac uzytkownikowi
     * liste aktywnych urzadzen.
     *
     * @param string[] $roles
     */
    public function issue(
        int $userId,
        int $tenantId,
        array $roles,
        ?string $userAgent = null,
        ?string $ip = null,
    ): TokenPair {
        $now = new DateTimeImmutable();

        $accessToken = $this->encode([
            'iss' => $this->issuer,
            'sub' => (string) $userId,
            'tid' => $tenantId,
            'roles' => array_values($roles),
            'iat' => $now->getTimestamp(),
            'exp' => $now->getTimestamp() + $this->accessTtl,
            'jti' => $this->newTokenId(),
            'typ' => 'access',
        ]);

        $refreshJti = $this->newTokenId();
        $refreshExpiresAt = $now->modify('+' . $this->refreshTtl . ' seconds');

        $refreshToken = $this->encode([
            'iss' => $this->issuer,
            'sub' => (string) $userId,
            'tid' => $tenantId,
            'iat' => $now->getTimestamp(),
            'exp' => $refreshExpiresAt->getTimestamp(),
            'jti' => $refreshJti,
            'typ' => 'refresh',
        ]);

        $this->refreshTokenStore->store(
            jti: $refreshJti,
            userId: $userId,
            tenantId: $tenantId,
            expiresAt: $refreshExpiresAt,
            userAgent: $userAgent,
            ip: $ip,
        );

        return new TokenPair(
            accessToken: $accessToken,
            accessExpiresIn: $this->accessTtl,
            refreshToken: $refreshToken,
            refreshExpiresIn: $this->refreshTtl,
        );
    }

    /**
     * Weryfikuje token dostepowy i zwraca tozsamosc dzwoniacego.
     *
     * @throws InvalidTokenException
     */
    public function authenticate(string $token): AuthenticatedUser
    {
        $claims = $this->decode($token);

        if (($claims['typ'] ?? null) !== 'access') {
            throw new InvalidTokenException('Oczekiwano tokenu dostepowego.');
        }

        return new AuthenticatedUser(
            userId: (int) $claims['sub'],
            tenantId: (int) $claims['tid'],
            // Claim `roles` przychodzi z zewnątrz (choć podpisany), więc
            // odrzucamy wszystko, co nie jest napisem, zamiast rzutować na siłę.
            roles: array_values(array_filter(
                (array) ($claims['roles'] ?? []),
                static fn(mixed $role): bool => is_string($role),
            )),
            tokenId: (string) $claims['jti'],
        );
    }

    /**
     * Wymienia wazny refresh token na nowa pare i uniewaznia stary (rotacja).
     *
     * Rotacja jest celowa: przechwycony refresh token przestaje dzialac
     * w momencie, gdy prawowity wlasciciel odswiezy sesje.
     *
     * @param string[] $roles Role sa odczytywane na nowo z bazy przez wolajacego.
     * @throws InvalidTokenException
     */
    public function refresh(
        string $refreshToken,
        array $roles,
        ?string $userAgent = null,
        ?string $ip = null,
    ): TokenPair {
        $claims = $this->decode($refreshToken);

        if (($claims['typ'] ?? null) !== 'refresh') {
            throw new InvalidTokenException('Oczekiwano tokenu odswiezajacego.');
        }

        $jti = (string) $claims['jti'];

        if (!$this->refreshTokenStore->isActive($jti)) {
            throw new InvalidTokenException('Token odswiezajacy zostal uniewazniony lub wygasl.');
        }

        $this->refreshTokenStore->revoke($jti);

        return $this->issue(
            userId: (int) $claims['sub'],
            tenantId: (int) $claims['tid'],
            roles: $roles,
            userAgent: $userAgent,
            ip: $ip,
        );
    }

    /**
     * Wylogowanie: uniewaznia konkretny refresh token.
     */
    public function revokeRefreshToken(string $refreshToken): void
    {
        try {
            $claims = $this->decode($refreshToken);
        } catch (InvalidTokenException) {
            // Token juz nie jest wazny - z punktu widzenia wylogowania to sukces.
            return;
        }

        $this->refreshTokenStore->revoke((string) $claims['jti']);
    }

    /**
     * Odczytuje identyfikator uzytkownika z refresh tokenu bez rotacji.
     * Potrzebne, zeby przed odswiezeniem pobrac aktualne role z bazy.
     *
     * @throws InvalidTokenException
     */
    public function refreshTokenSubject(string $refreshToken): int
    {
        return (int) $this->decode($refreshToken)['sub'];
    }

    /**
     * @param array<string, mixed> $claims
     */
    private function encode(array $claims): string
    {
        return JWT::encode($claims, $this->secret, $this->algorithm);
    }

    /**
     * @return array<string, mixed>
     * @throws InvalidTokenException
     */
    private function decode(string $token): array
    {
        try {
            $claims = (array) JWT::decode($token, new Key($this->secret, $this->algorithm));
        } catch (ExpiredException) {
            throw new InvalidTokenException('Token wygasl.');
        } catch (Throwable) {
            throw new InvalidTokenException('Token jest niepoprawny.');
        }

        if (($claims['iss'] ?? null) !== $this->issuer) {
            throw new InvalidTokenException('Token pochodzi z innego wystawcy.');
        }

        return $claims;
    }

    private function newTokenId(): string
    {
        return bin2hex(random_bytes(16));
    }
}
