<?php

declare(strict_types=1);

namespace App\Module\Aml\Client;

use App\Module\Aml\Dto\AmlRiskScore;
use App\Shared\ExternalApi\ExternalApiException;

/**
 * Kontrakt rozmowy z zewnetrzna aplikacja AML.
 *
 * Kod domenowy zna wylacznie ten interfejs. Dzieki temu w testach podstawiamy
 * {@see FakeAmlApiClient} i nie potrzebujemy dzialajacej instancji AML.
 */
interface AmlApiClientInterface
{
    /**
     * @throws ExternalApiException gdy aplikacja AML jest niedostepna lub odmowila.
     */
    public function riskScoreFor(int $tenantId, int $clientId): AmlRiskScore;

    /**
     * Alerty oczekujace na weryfikacje analityka.
     *
     * @return AmlRiskScore[]
     * @throws ExternalApiException
     */
    public function pendingAlerts(int $tenantId): array;

    /**
     * Zleca ponowne przeliczenie ryzyka. Zwraca identyfikator zadania -
     * przeliczanie jest asynchroniczne po stronie aplikacji AML.
     *
     * @throws ExternalApiException
     */
    public function requestRescan(int $tenantId, int $clientId): string;
}
