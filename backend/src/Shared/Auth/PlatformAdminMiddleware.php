<?php

declare(strict_types=1);

namespace App\Shared\Auth;

use App\Shared\Http\JsonResponse;
use App\Shared\Tenant\TenantMiddleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Wpuszcza dalej wylacznie administratora calego systemu.
 *
 * Stoi ZA {@see \App\Shared\Tenant\TenantMiddleware}, ktore zdazylo juz
 * zweryfikowac podpis tokenu i wstawic tozsamosc do atrybutu zadania.
 * Rola jest czytana z tego atrybutu, a nie z ciala zadania czy naglowka -
 * pochodzi wiec z podpisanego tokenu, ktorego klient nie podrobi.
 *
 * Token zyje 15 minut i przy kazdym odswiezeniu role sa odczytywane z bazy
 * na nowo (patrz JwtService::refresh), wiec odebranie roli dziala najpozniej
 * po kwadransie, a nie po wygasnieciu refresh tokenu.
 */
final readonly class PlatformAdminMiddleware implements MiddlewareInterface
{
    public function __construct(
        private JsonResponse $json,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $user = $request->getAttribute(TenantMiddleware::REQUEST_ATTRIBUTE);

        if (!$user instanceof AuthenticatedUser) {
            // Zdarza sie tylko wtedy, gdy ktos wpial to middleware bez
            // TenantMiddleware przed nim. Lepiej odmowic niz wpuscic.
            return $this->json->unauthorized('Wymagane uwierzytelnienie.');
        }

        if (!Role::isPlatformAdmin($user->roles)) {
            return $this->json->forbidden('Ta operacja wymaga uprawnien administratora systemu.');
        }

        return $handler->handle($request);
    }
}
