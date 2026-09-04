<?php

declare(strict_types=1);

namespace App\Module\Delegation\Client;

use App\Module\Delegation\Dto\DelegationReport;
use App\Shared\ExternalApi\ExternalApiException;
use GuzzleHttp\ClientInterface;
use Throwable;

/**
 * STUB. Klient HTTP do DelegoApp.
 *
 * Adres i klucz API sa puste do czasu wpiecia DelegoApp
 * (patrz `solidus.externalApi.delegation` w config/common/params.php).
 */
final readonly class HttpDelegationApiClient implements DelegationApiClientInterface
{
    public function __construct(
        private ClientInterface $httpClient,
        private string $baseUrl,
        private string $apiKey,
        private int $timeout = 10,
    ) {}

    public function reportsForClient(int $tenantId, int $clientId): array
    {
        $payload = $this->request('GET', '/v1/reports', $tenantId, ['clientId' => $clientId]);

        return array_map(
            DelegationReport::fromApiPayload(...),
            (array) ($payload['items'] ?? []),
        );
    }

    public function report(int $tenantId, string $reportId): DelegationReport
    {
        return DelegationReport::fromApiPayload(
            $this->request('GET', "/v1/reports/{$reportId}", $tenantId),
        );
    }

    public function approveReport(int $tenantId, string $reportId): DelegationReport
    {
        return DelegationReport::fromApiPayload(
            $this->request('POST', "/v1/reports/{$reportId}/approve", $tenantId),
        );
    }

    /**
     * @param array<string, scalar> $query
     * @return array<string, mixed>
     * @throws ExternalApiException
     */
    private function request(string $method, string $path, int $tenantId, array $query = []): array
    {
        if ($this->baseUrl === '') {
            throw new ExternalApiException(
                'Adres DelegoApp nie zostal skonfigurowany (DELEGO_API_URL).',
            );
        }

        try {
            $response = $this->httpClient->request($method, rtrim($this->baseUrl, '/') . $path, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'X-Tenant-Id' => (string) $tenantId,
                    'Accept' => 'application/json',
                ],
                'query' => $query,
                'timeout' => $this->timeout,
            ]);

            $decoded = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            throw new ExternalApiException(
                'DelegoApp nie odpowiedzialo poprawnie: ' . $e->getMessage(),
                previous: $e,
            );
        }

        return is_array($decoded) ? $decoded : [];
    }
}
