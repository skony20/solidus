<?php

declare(strict_types=1);

namespace App\Module\Delegation\Client;

use App\Module\Delegation\Dto\DelegationReport;
use App\Shared\ExternalApi\ExternalApiException;

/**
 * Implementacja testowa DelegoApp - pozwala pisac testy modulu Delegacje
 * bez dzialajacej aplikacji zewnetrznej.
 */
final class FakeDelegationApiClient implements DelegationApiClientInterface
{
    /** @var array<string, DelegationReport> */
    private array $reports = [];

    /** @var string[] */
    private array $approved = [];

    public function withReport(DelegationReport $report): self
    {
        $this->reports[$report->reportId] = $report;

        return $this;
    }

    public function reportsForClient(int $tenantId, int $clientId): array
    {
        return array_values(array_filter(
            $this->reports,
            static fn(DelegationReport $r) => $r->clientId === $clientId,
        ));
    }

    public function report(int $tenantId, string $reportId): DelegationReport
    {
        return $this->reports[$reportId]
            ?? throw new ExternalApiException("Brak rozliczenia {$reportId}.");
    }

    public function approveReport(int $tenantId, string $reportId): DelegationReport
    {
        $this->approved[] = $reportId;

        return $this->report($tenantId, $reportId);
    }

    /**
     * @return string[]
     */
    public function approvedReports(): array
    {
        return $this->approved;
    }
}
