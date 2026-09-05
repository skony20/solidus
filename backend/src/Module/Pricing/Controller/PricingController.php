<?php

declare(strict_types=1);

namespace App\Module\Pricing\Controller;

use App\Module\Pricing\Dto\PricingPlanInput;
use App\Module\Pricing\Repository\PricingPlanRepository;
use App\Module\Pricing\Service\PricingPlanNotFoundException;
use App\Module\Pricing\Service\PricingService;
use App\Shared\Http\ApiController;
use App\Shared\Http\JsonResponse;
use App\Shared\Validation\ValidationException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Router\CurrentRoute;

/**
 * Cennik: jedno wejscie publiczne i CRUD dla administratora systemu.
 *
 * Rozdzial jest w trasach, nie w kodzie kontrolera: `index()` wisi na trasie
 * bez zadnego middleware uwierzytelniajacego, a `create/update/delete` w grupie
 * za TenantMiddleware + PlatformAdminMiddleware. Kontroler nie sprawdza
 * uprawnien sam - gdyby to robil, kazda nowa metoda byla by okazja do
 * zapomnienia o warunku.
 *
 * `index()` zwraca wylacznie plany aktywne; `adminIndex()` takze ukryte, bo
 * administrator musi je widziec, zeby moc wlaczyc je z powrotem.
 */
final readonly class PricingController extends ApiController
{
    public function __construct(
        JsonResponse $json,
        private PricingPlanRepository $repository,
        private PricingService $service,
        private CurrentRoute $currentRoute,
    ) {
        parent::__construct($json);
    }

    /** GET /api/pricing - publiczne, zasila sekcje cennika na stronie. */
    public function index(): ResponseInterface
    {
        return $this->json->ok([
            'items' => array_map(
                static fn($plan) => $plan->toArray(),
                $this->repository->findAll(onlyActive: true),
            ),
        ]);
    }

    /** GET /api/admin/pricing - takze plany wylaczone. */
    public function adminIndex(): ResponseInterface
    {
        return $this->json->ok([
            'items' => array_map(
                static fn($plan) => $plan->toArray(),
                $this->repository->findAll(onlyActive: false),
            ),
        ]);
    }

    public function create(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $input = PricingPlanInput::fromArray($this->body($request));
            $plan = $this->service->create($input, $this->clientIp($request));
        } catch (ValidationException $e) {
            return $this->json->unprocessable($e->getMessage(), $e->errors());
        }

        return $this->json->created(['item' => $plan->toArray()]);
    }

    public function update(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $input = PricingPlanInput::fromArray($this->body($request));
            $plan = $this->service->update($this->routeId(), $input, $this->clientIp($request));
        } catch (ValidationException $e) {
            return $this->json->unprocessable($e->getMessage(), $e->errors());
        } catch (PricingPlanNotFoundException $e) {
            return $this->json->notFound($e->getMessage());
        }

        return $this->json->ok(['item' => $plan->toArray()]);
    }

    public function delete(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $this->service->delete($this->routeId(), $this->clientIp($request));
        } catch (PricingPlanNotFoundException $e) {
            return $this->json->notFound($e->getMessage());
        }

        return $this->json->noContent();
    }

    private function routeId(): int
    {
        return (int) $this->currentRoute->getArgument('id');
    }

    private function clientIp(ServerRequestInterface $request): ?string
    {
        $server = $request->getServerParams();
        $ip = $server['REMOTE_ADDR'] ?? null;

        return is_string($ip) && $ip !== '' ? $ip : null;
    }
}
