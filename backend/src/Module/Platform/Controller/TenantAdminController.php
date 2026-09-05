<?php

declare(strict_types=1);

namespace App\Module\Platform\Controller;

use App\Module\Platform\Dto\TenantPaymentInput;
use App\Module\Platform\Service\TenantAdminService;
use App\Module\Platform\Service\TenantNotFoundException;
use App\Shared\Auth\AuthenticatedUser;
use App\Shared\Http\ApiController;
use App\Shared\Http\JsonResponse;
use App\Shared\Tenant\TenantMiddleware;
use App\Shared\Validation\ValidationException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Router\CurrentRoute;

/**
 * Panel operatora Solidusa: przeglad biur, zmiana ich stanu, przypisanie
 * planu i reczne ksiegowanie platnosci.
 *
 * Cala grupa tras `/api/admin/tenants` stoi za TenantMiddleware +
 * PlatformAdminMiddleware (patrz routes.php) - kontroler nie sprawdza
 * uprawnien sam, dokladnie jak PricingController.
 */
final readonly class TenantAdminController extends ApiController
{
    public function __construct(
        JsonResponse $json,
        private TenantAdminService $service,
        private CurrentRoute $currentRoute,
    ) {
        parent::__construct($json);
    }

    /** GET /api/admin/tenants */
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $query = $request->getQueryParams();
        $search = isset($query['search']) ? trim((string) $query['search']) : null;
        $status = isset($query['status']) ? trim((string) $query['status']) : null;
        $limit = min(max((int) ($query['limit'] ?? 50), 1), 200);
        $offset = max((int) ($query['offset'] ?? 0), 0);

        return $this->json->ok([
            'items' => array_map(
                static fn($overview) => $overview->toArray(),
                $this->service->list($search, $status, $limit, $offset),
            ),
            'total' => $this->service->count($search, $status),
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }

    /** GET /api/admin/tenants/{id} */
    public function view(): ResponseInterface
    {
        try {
            $tenant = $this->service->find($this->routeId());

            return $this->json->ok([
                'item' => $tenant->toArray(),
                'users' => $this->service->usersFor($tenant->tenant->id),
                'payments' => array_map(
                    static fn($payment) => $payment->toArray(),
                    $this->service->paymentsFor($tenant->tenant->id),
                ),
            ]);
        } catch (TenantNotFoundException $e) {
            return $this->json->notFound($e->getMessage());
        }
    }

    /** PUT /api/admin/tenants/{id}/status - body: {status} */
    public function updateStatus(ServerRequestInterface $request): ResponseInterface
    {
        $body = $this->body($request);
        $status = (string) ($body['status'] ?? '');

        try {
            $tenant = $this->service->changeStatus($this->routeId(), $status, $this->clientIp($request));
        } catch (ValidationException $e) {
            return $this->json->unprocessable($e->getMessage(), $e->errors());
        } catch (TenantNotFoundException $e) {
            return $this->json->notFound($e->getMessage());
        }

        return $this->json->ok(['item' => $tenant->toArray()]);
    }

    /** PUT /api/admin/tenants/{id}/plan - body: {pricingPlanId: int|null} */
    public function updatePlan(ServerRequestInterface $request): ResponseInterface
    {
        $body = $this->body($request);
        $rawPlanId = $body['pricingPlanId'] ?? null;
        $pricingPlanId = $rawPlanId === null || $rawPlanId === '' ? null : (int) $rawPlanId;

        try {
            $tenant = $this->service->assignPlan($this->routeId(), $pricingPlanId, $this->clientIp($request));
        } catch (ValidationException $e) {
            return $this->json->unprocessable($e->getMessage(), $e->errors());
        } catch (TenantNotFoundException $e) {
            return $this->json->notFound($e->getMessage());
        }

        return $this->json->ok(['item' => $tenant->toArray()]);
    }

    /** POST /api/admin/tenants/{id}/payments */
    public function recordPayment(ServerRequestInterface $request): ResponseInterface
    {
        $identity = $request->getAttribute(TenantMiddleware::REQUEST_ATTRIBUTE);
        if (!$identity instanceof AuthenticatedUser) {
            return $this->json->unauthorized();
        }

        try {
            $input = TenantPaymentInput::fromArray($this->body($request));
            $payment = $this->service->recordPayment(
                $this->routeId(),
                $input,
                $identity->userId,
                $this->clientIp($request),
            );
        } catch (ValidationException $e) {
            return $this->json->unprocessable($e->getMessage(), $e->errors());
        } catch (TenantNotFoundException $e) {
            return $this->json->notFound($e->getMessage());
        }

        return $this->json->created(['item' => $payment->toArray()]);
    }

    private function routeId(): int
    {
        return (int) $this->currentRoute->getArgument('id');
    }

    private function clientIp(ServerRequestInterface $request): ?string
    {
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? null;

        return is_string($ip) && $ip !== '' ? $ip : null;
    }
}
