<?php

declare(strict_types=1);

namespace App\Shared\Tenant;

use App\Shared\Auth\InvalidTokenException;
use App\Shared\Auth\JwtService;
use App\Shared\Http\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Bramka kazdego chronionego endpointu API.
 *
 * Czyta naglowek `Authorization: Bearer ...`, weryfikuje token i wstawia
 * tenanta (claim `tid`) oraz uzytkownika do kontekstu zadania. Od tego
 * momentu repozytoria same filtruja dane po tenancie - kod domenowy nie musi
 * (i nie powinien) przekazywac tenant_id recznie.
 *
 * Tozsamosc trafia tez do atrybutu zadania, gdyby ktorys kontroler wolal
 * czytac ja wprost z PSR-7 zamiast z kontekstu.
 */
final readonly class TenantMiddleware implements MiddlewareInterface
{
    public const REQUEST_ATTRIBUTE = 'solidus.user';

    public function __construct(
        private JwtService $jwtService,
        private TenantContext $tenantContext,
        private JsonResponse $json,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $token = $this->extractToken($request);

        if ($token === null) {
            return $this->json->unauthorized('Brak tokenu dostepowego.');
        }

        try {
            $user = $this->jwtService->authenticate($token);
        } catch (InvalidTokenException $e) {
            return $this->json->unauthorized($e->getMessage());
        }

        $this->tenantContext->set($user->tenantId, $user->userId, $user->roles);

        try {
            return $handler->handle($request->withAttribute(self::REQUEST_ATTRIBUTE, $user));
        } finally {
            // Kontener trzyma TenantContext jako singleton; czyscimy, zeby
            // przy dlugo zyjacym procesie (np. RoadRunner) nie wyciekl tenant
            // z poprzedniego zadania.
            $this->tenantContext->clear();
        }
    }

    private function extractToken(ServerRequestInterface $request): ?string
    {
        $header = $request->getHeaderLine('Authorization');

        if (preg_match('/^Bearer\s+(.+)$/i', $header, $matches) !== 1) {
            return null;
        }

        return trim($matches[1]);
    }
}
