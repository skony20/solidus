<?php

declare(strict_types=1);

namespace App\Module\Platform\Entity;

use DateTimeImmutable;

/**
 * Jedna platnosc abonamentowa biura - wiersz historii rozliczen.
 *
 * DLACZEGO OSOBNA TABELA, A NIE POLE NA `tenants`: abonament to historia, nie
 * stan. "Opłacone do kiedy" da się policzyć z ostatniego wiersza, ale samo to
 * pole zgubiłoby ślad po tym, ile razy i kiedy biuro faktycznie płaciło -
 * a to pierwsze pytanie przy sporze o fakturę.
 *
 * `provider` to dzis prawie zawsze 'manual' - operator recznie odnotowuje
 * przelew. Kolumny `provider` i `providerReference` istnieja od poczatku
 * (nie sa dodawane pozniej), zeby przyszla integracja z operatorem platnosci
 * (Stripe, Przelewy24, PayU...) byla dopisaniem klienta API, a nie zmiana
 * schematu i migracja istniejacych wierszy.
 */
final readonly class TenantPayment
{
    public function __construct(
        public ?int $id,
        public int $tenantId,
        /** Kwota w GROSZACH - ten sam wzorzec co w Module\Pricing. */
        public int $amount,
        public string $currency,
        public DateTimeImmutable $periodStart,
        public DateTimeImmutable $periodEnd,
        public PaymentStatus $status,
        public string $provider,
        public ?string $providerReference,
        public ?string $note,
        /** Kto recznie odnotowal platnosc - null przy przyszlej platnosci automatycznej. */
        public ?int $recordedByUserId,
        public DateTimeImmutable $createdAt,
    ) {}

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            tenantId: (int) $row['tenant_id'],
            amount: (int) $row['amount'],
            currency: (string) $row['currency'],
            periodStart: new DateTimeImmutable((string) $row['period_start']),
            periodEnd: new DateTimeImmutable((string) $row['period_end']),
            status: PaymentStatus::from((string) $row['status']),
            provider: (string) $row['provider'],
            providerReference: self::nullableString($row['provider_reference'] ?? null),
            note: self::nullableString($row['note'] ?? null),
            recordedByUserId: isset($row['recorded_by_user_id']) ? (int) $row['recorded_by_user_id'] : null,
            createdAt: new DateTimeImmutable((string) $row['created_at']),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'tenantId' => $this->tenantId,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'periodStart' => $this->periodStart->format('Y-m-d'),
            'periodEnd' => $this->periodEnd->format('Y-m-d'),
            'status' => $this->status->value,
            'statusLabel' => $this->status->label(),
            'provider' => $this->provider,
            'providerReference' => $this->providerReference,
            'note' => $this->note,
            'createdAt' => $this->createdAt->format(DATE_ATOM),
        ];
    }

    private static function nullableString(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : (string) $value;
    }
}
