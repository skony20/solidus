<?php

declare(strict_types=1);

namespace App\Module\Whistleblower\Client;

use App\Module\Whistleblower\Dto\WhistleblowerCase;
use App\Shared\ExternalApi\ExternalApiException;
use DateTimeImmutable;

/**
 * Implementacja testowa kanalu sygnalistow.
 */
final class FakeWhistleblowerApiClient implements WhistleblowerApiClientInterface
{
    /** @var array<string, WhistleblowerCase> */
    private array $cases = [];

    public function withCase(WhistleblowerCase $case): self
    {
        $this->cases[$case->caseId] = $case;

        return $this;
    }

    public function openCases(int $tenantId): array
    {
        return array_values(array_filter(
            $this->cases,
            static fn(WhistleblowerCase $c) => $c->status !== WhistleblowerCase::STATUS_CLOSED,
        ));
    }

    public function caseMetadata(int $tenantId, string $caseId): WhistleblowerCase
    {
        return $this->cases[$caseId]
            ?? throw new ExternalApiException("Brak zgloszenia {$caseId}.");
    }

    public function overdueCount(int $tenantId): int
    {
        $now = new DateTimeImmutable();

        return count(array_filter(
            $this->openCases($tenantId),
            static fn(WhistleblowerCase $c) => $c->responseDeadline !== null && $c->responseDeadline < $now,
        ));
    }
}
