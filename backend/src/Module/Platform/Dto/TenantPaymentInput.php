<?php

declare(strict_types=1);

namespace App\Module\Platform\Dto;

use App\Module\Platform\Entity\PaymentStatus;
use App\Shared\Validation\ValidationException;
use DateTimeImmutable;

/**
 * Dane recznie wpisywanej platnosci, juz sprawdzone.
 */
final readonly class TenantPaymentInput
{
    public function __construct(
        public int $amount,
        public string $currency,
        public DateTimeImmutable $periodStart,
        public DateTimeImmutable $periodEnd,
        public PaymentStatus $status,
        public string $provider,
        public ?string $providerReference,
        public ?string $note,
    ) {}

    /**
     * @param array<string, mixed> $data
     *
     * @throws ValidationException
     */
    public static function fromArray(array $data): self
    {
        $errors = [];

        $amount = is_numeric($data['amount'] ?? null) ? (int) $data['amount'] : null;
        if ($amount === null || $amount <= 0) {
            $errors['amount'][] = 'Kwota musi byc dodatnia liczba groszy.';
        }

        $currency = mb_strtoupper(trim((string) ($data['currency'] ?? 'PLN')));
        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            $errors['currency'][] = 'Waluta musi byc trzyliterowym kodem ISO, np. PLN.';
        }

        $periodStart = self::parseDate($data['periodStart'] ?? null);
        if ($periodStart === false) {
            $errors['periodStart'][] = 'Podaj poprawna date poczatku okresu (RRRR-MM-DD).';
        }

        $periodEnd = self::parseDate($data['periodEnd'] ?? null);
        if ($periodEnd === false) {
            $errors['periodEnd'][] = 'Podaj poprawna date konca okresu (RRRR-MM-DD).';
        } elseif ($periodStart !== false && $periodEnd < $periodStart) {
            $errors['periodEnd'][] = 'Koniec okresu nie moze byc wczesniejszy niz jego poczatek.';
        }

        $status = PaymentStatus::tryFrom((string) ($data['status'] ?? 'paid'));
        if ($status === null) {
            $errors['status'][] = 'Nieznany stan platnosci.';
        }

        $provider = mb_strtolower(trim((string) ($data['provider'] ?? 'manual')));
        if (preg_match('/^[a-z0-9_-]{2,30}$/', $provider) !== 1) {
            $errors['provider'][] = 'Zrodlo platnosci moze zawierac tylko male litery, cyfry, _ i - (2-30 znakow).';
        }

        $providerReference = self::nullableText($data['providerReference'] ?? null, 120);
        if ($providerReference === false) {
            $errors['providerReference'][] = 'Identyfikator transakcji moze miec najwyzej 120 znakow.';
        }

        $note = self::nullableText($data['note'] ?? null, 255);
        if ($note === false) {
            $errors['note'][] = 'Notatka moze miec najwyzej 255 znakow.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        /** @var int $amount */
        return new self(
            amount: $amount,
            currency: $currency,
            periodStart: $periodStart,
            periodEnd: $periodEnd,
            status: $status,
            provider: $provider,
            providerReference: $providerReference === false ? null : $providerReference,
            note: $note === false ? null : $note,
        );
    }

    private static function parseDate(mixed $value): DateTimeImmutable|false
    {
        if (!is_string($value) || $value === '') {
            return false;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date === false ? false : $date;
    }

    /**
     * @return string|false|null false oznacza przekroczona dlugosc.
     */
    private static function nullableText(mixed $value, int $maxLength): string|false|null
    {
        $text = trim((string) ($value ?? ''));

        if ($text === '') {
            return null;
        }

        return mb_strlen($text) > $maxLength ? false : $text;
    }
}
