<?php

declare(strict_types=1);

namespace App\Module\Delegation\Dto;

use DateTimeImmutable;

/**
 * Kontrakt raportu delegacji z zewnetrznej aplikacji DelegoApp.
 *
 * Solidus nie prowadzi ewidencji delegacji - pobiera gotowe rozliczenie,
 * zeby ksiegowa zobaczyla je obok reszty dokumentow klienta.
 */
final readonly class DelegationReport
{
    /**
     * @param string $currency Kod ISO 4217, np. "PLN".
     * @param array<int, array<string, mixed>> $items Pozycje rozliczenia (diety, noclegi, przejazdy).
     */
    public function __construct(
        public string $reportId,
        public int $clientId,
        public string $employeeName,
        public string $destination,
        public DateTimeImmutable $startDate,
        public DateTimeImmutable $endDate,
        public string $status,
        public float $totalAmount,
        public string $currency,
        public array $items,
    ) {}

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromApiPayload(array $payload): self
    {
        return new self(
            reportId: (string) ($payload['reportId'] ?? ''),
            clientId: (int) ($payload['clientId'] ?? 0),
            employeeName: (string) ($payload['employeeName'] ?? ''),
            destination: (string) ($payload['destination'] ?? ''),
            startDate: new DateTimeImmutable((string) ($payload['startDate'] ?? 'now')),
            endDate: new DateTimeImmutable((string) ($payload['endDate'] ?? 'now')),
            status: (string) ($payload['status'] ?? 'draft'),
            totalAmount: (float) ($payload['totalAmount'] ?? 0.0),
            currency: (string) ($payload['currency'] ?? 'PLN'),
            items: (array) ($payload['items'] ?? []),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'reportId' => $this->reportId,
            'clientId' => $this->clientId,
            'employeeName' => $this->employeeName,
            'destination' => $this->destination,
            'startDate' => $this->startDate->format('Y-m-d'),
            'endDate' => $this->endDate->format('Y-m-d'),
            'status' => $this->status,
            'totalAmount' => $this->totalAmount,
            'currency' => $this->currency,
            'items' => $this->items,
        ];
    }
}
