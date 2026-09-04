<?php

declare(strict_types=1);

namespace App\Module\Delegation\Client;

use App\Module\Delegation\Dto\DelegationReport;
use App\Shared\ExternalApi\ExternalApiException;

/**
 * Kontrakt rozmowy z DelegoApp - zewnetrzna aplikacja do delegacji.
 */
interface DelegationApiClientInterface
{
    /**
     * Rozliczenia delegacji dla jednego klienta biura.
     *
     * @return DelegationReport[]
     * @throws ExternalApiException
     */
    public function reportsForClient(int $tenantId, int $clientId): array;

    /**
     * @throws ExternalApiException
     */
    public function report(int $tenantId, string $reportId): DelegationReport;

    /**
     * Zatwierdza rozliczenie po stronie DelegoApp - dopiero wtedy trafia
     * ono do ksiegowania.
     *
     * @throws ExternalApiException
     */
    public function approveReport(int $tenantId, string $reportId): DelegationReport;
}
