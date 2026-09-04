<?php

declare(strict_types=1);

namespace App\Module\Aml\Client;

use App\Module\Aml\Dto\AmlRiskScore;
use DateTimeImmutable;

/**
 * Implementacja na potrzeby testow i pracy bez dostepu do aplikacji AML.
 *
 * Zwraca deterministyczne dane, wiec testy nie zaleza od sieci ani od tego,
 * czy zewnetrzna aplikacja akurat dziala.
 */
final class FakeAmlApiClient implements AmlApiClientInterface
{
    /** @var array<int, AmlRiskScore> */
    private array $scores = [];

    /** @var string[] Slad wywolan - do asercji w testach. */
    private array $rescans = [];

    public function withScore(AmlRiskScore $score): self
    {
        $this->scores[$score->clientId] = $score;

        return $this;
    }

    public function riskScoreFor(int $tenantId, int $clientId): AmlRiskScore
    {
        return $this->scores[$clientId] ?? new AmlRiskScore(
            clientId: $clientId,
            score: 12,
            level: AmlRiskScore::LEVEL_LOW,
            factors: [],
            calculatedAt: new DateTimeImmutable(),
        );
    }

    public function pendingAlerts(int $tenantId): array
    {
        return array_values(array_filter(
            $this->scores,
            static fn(AmlRiskScore $s) => $s->level !== AmlRiskScore::LEVEL_LOW,
        ));
    }

    public function requestRescan(int $tenantId, int $clientId): string
    {
        $jobId = 'fake-job-' . $clientId;
        $this->rescans[] = $jobId;

        return $jobId;
    }

    /**
     * @return string[]
     */
    public function rescans(): array
    {
        return $this->rescans;
    }
}
