<?php

declare(strict_types=1);

namespace App\Module\Account\Service;

use DateTimeImmutable;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Cookies\Cookie;

/**
 * Osadza refresh token w ciasteczku httpOnly / Secure / SameSite=Strict.
 *
 * Ciasteczko jest ograniczone sciezka do /api/auth, wiec nie jest wysylane
 * przy zwyklych zapytaniach do API - tam jezdzi wylacznie krotki access token.
 * Klient mobilny nie bedzie uzywal tej klasy; dostanie refresh token w ciele
 * odpowiedzi, korzystajac z tego samego {@see \App\Shared\Auth\JwtService}.
 */
final readonly class RefreshCookie
{
    public function __construct(
        private string $name,
        private string $path,
        private int $ttl,
        private bool $secure,
    ) {}

    public function attach(ResponseInterface $response, string $token): ResponseInterface
    {
        return (new Cookie(
            name: $this->name,
            value: $token,
            expires: (new DateTimeImmutable())->modify('+' . $this->ttl . ' seconds'),
            path: $this->path,
            secure: $this->secure,
            httpOnly: true,
            sameSite: Cookie::SAME_SITE_STRICT,
        ))->addToResponse($response);
    }

    public function clear(ResponseInterface $response): ResponseInterface
    {
        return (new Cookie(
            name: $this->name,
            value: '',
            expires: (new DateTimeImmutable())->modify('-1 day'),
            path: $this->path,
            secure: $this->secure,
            httpOnly: true,
            sameSite: Cookie::SAME_SITE_STRICT,
        ))->addToResponse($response);
    }

    public function read(ServerRequestInterface $request): ?string
    {
        $cookies = $request->getCookieParams();
        $value = $cookies[$this->name] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
