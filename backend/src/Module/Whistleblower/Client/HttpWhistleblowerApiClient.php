<?php

declare(strict_types=1);

namespace App\Module\Whistleblower\Client;

use App\Module\Whistleblower\Dto\WhistleblowerCase;
use App\Shared\ExternalApi\ExternalApiException;
use GuzzleHttp\ClientInterface;
use Throwable;

/**
 * STUB. Klient HTTP do aplikacji kanalu sygnalistow.
 *
 * Adres i klucz API do uzupelnienia
 * (patrz `solidus.externalApi.whistleblower` w config/common/params.php).
 */
final readonly class HttpWhistleblowerApiClient implements WhistleblowerApiClientInterface
{
    public function __construct(
        private ClientInterface $httpClient,
        private string $baseUrl,
        private string $apiKey,
        private int $timeout = 10,
    ) {}

    public function openCases(int $tenantId): array
    {
        $payload = $this->request('GET', '/v1/cases', $tenantId, ['status' => 'open']);

        return array_map(
            WhistleblowerCase::fromApiPayload(...),
            (array) ($payload['items'] ?? []),
        );
    }

    public function caseMetadata(int $tenantId, string $caseId): WhistleblowerCase
    {
        return WhistleblowerCase::fromApiPayload(
            $this->request('GET', "/v1/cases/{$caseId}", $tenantId),
        );
    }

    public function overdueCount(int $tenantId): int
    {
        return (int) ($this->request('GET', '/v1/cases/overdue-count', $tenantId)['count'] ?? 0);
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
                'Adres aplikacji sygnalistow nie zostal skonfigurowany (WHISTLEBLOWER_API_URL).',
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
                'Aplikacja sygnalistow nie odpowiedziala poprawnie: ' . $e->getMessage(),
                previous: $e,
            );
        }

        return is_array($decoded) ? $decoded : [];
    }
}
