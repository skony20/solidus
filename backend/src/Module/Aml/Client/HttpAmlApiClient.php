<?php

declare(strict_types=1);

namespace App\Module\Aml\Client;

use App\Module\Aml\Dto\AmlRiskScore;
use App\Shared\ExternalApi\ExternalApiException;
use GuzzleHttp\ClientInterface;
use Throwable;

/**
 * STUB. Rozmawia z zewnetrzna aplikacja AML po HTTP.
 *
 * Adresy i klucz API sa puste do czasu, az aplikacja AML powstanie
 * (patrz `solidus.externalApi.aml` w config/common/params.php). Ksztalt
 * zadan i odpowiedzi jest propozycja kontraktu do potwierdzenia po obu
 * stronach - stad brak testow na tej klasie.
 */
final readonly class HttpAmlApiClient implements AmlApiClientInterface
{
    public function __construct(
        private ClientInterface $httpClient,
        private string $baseUrl,
        private string $apiKey,
        private int $timeout = 10,
    ) {}

    public function riskScoreFor(int $tenantId, int $clientId): AmlRiskScore
    {
        return AmlRiskScore::fromApiPayload(
            $this->get("/v1/clients/{$clientId}/risk-score", $tenantId),
        );
    }

    public function pendingAlerts(int $tenantId): array
    {
        $payload = $this->get('/v1/alerts', $tenantId, ['status' => 'pending']);

        return array_map(
            AmlRiskScore::fromApiPayload(...),
            (array) ($payload['items'] ?? []),
        );
    }

    public function requestRescan(int $tenantId, int $clientId): string
    {
        $payload = $this->request('POST', "/v1/clients/{$clientId}/rescan", $tenantId);

        return (string) ($payload['jobId'] ?? '');
    }

    /**
     * @param array<string, scalar> $query
     * @return array<string, mixed>
     */
    private function get(string $path, int $tenantId, array $query = []): array
    {
        return $this->request('GET', $path, $tenantId, $query);
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
                'Adres aplikacji AML nie zostal skonfigurowany (AML_API_URL).',
            );
        }

        try {
            $response = $this->httpClient->request($method, rtrim($this->baseUrl, '/') . $path, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    // Tenant jedzie w naglowku, bo aplikacja AML obsluguje
                    // wiele biur i musi wiedziec, czyje dane liczy.
                    'X-Tenant-Id' => (string) $tenantId,
                    'Accept' => 'application/json',
                ],
                'query' => $query,
                'timeout' => $this->timeout,
            ]);

            $decoded = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            throw new ExternalApiException(
                'Aplikacja AML nie odpowiedziala poprawnie: ' . $e->getMessage(),
                previous: $e,
            );
        }

        return is_array($decoded) ? $decoded : [];
    }
}
