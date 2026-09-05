<?php

declare(strict_types=1);

namespace App\Module\Client\Controller;

use App\Module\Client\Dto\ClientInput;
use App\Module\Client\Repository\ClientRepository;
use App\Module\Client\Service\ClientNotFoundException;
use App\Module\Client\Service\ClientService;
use App\Shared\Http\ApiController;
use App\Shared\Http\JsonResponse;
use App\Shared\Validation\ValidationException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Router\CurrentRoute;

/**
 * REST API modulu Klienci - wzorcowy kontroler Solidusa.
 *
 * Kontroler jest cienki: tlumaczy HTTP na wywolanie serwisu i z powrotem.
 * Nie zna SQL-a, nie zna tenanta (robi to middleware) i nie pisze do audit
 * logu (robi to serwis).
 */
final readonly class ClientController extends ApiController
{
    public function __construct(
        JsonResponse $json,
        private ClientRepository $repository,
        private ClientService $service,
        private CurrentRoute $currentRoute,
    ) {
        parent::__construct($json);
    }

    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $query = $request->getQueryParams();
        $search = isset($query['search']) ? trim((string) $query['search']) : null;
        $status = isset($query['status']) ? trim((string) $query['status']) : null;
        $limit = min(max((int) ($query['limit'] ?? 50), 1), 200);
        $offset = max((int) ($query['offset'] ?? 0), 0);

        $clients = $this->repository->findAll($search, $status, $limit, $offset);

        return $this->json->ok([
            'items' => array_map(static fn($client) => $client->toArray(), $clients),
            'total' => $this->repository->count($search, $status),
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }

    public function view(): ResponseInterface
    {
        $client = $this->repository->findById($this->routeId());

        if ($client === null) {
            return $this->json->notFound('Nie znaleziono klienta.');
        }

        return $this->json->ok(['item' => $client->toArray()]);
    }

    public function create(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $input = ClientInput::fromArray($this->body($request));
            $client = $this->service->create($input, $this->clientIp($request));
        } catch (ValidationException $e) {
            return $this->json->unprocessable($e->getMessage(), $e->errors());
        }

        return $this->json->created(['item' => $client->toArray()]);
    }

    public function update(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $input = ClientInput::fromArray($this->body($request));
            $client = $this->service->update($this->routeId(), $input, $this->clientIp($request));
        } catch (ValidationException $e) {
            return $this->json->unprocessable($e->getMessage(), $e->errors());
        } catch (ClientNotFoundException $e) {
            return $this->json->notFound($e->getMessage());
        }

        return $this->json->ok(['item' => $client->toArray()]);
    }

    public function delete(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $this->service->delete($this->routeId(), $this->clientIp($request));
        } catch (ClientNotFoundException $e) {
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
