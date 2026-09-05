<?php

declare(strict_types=1);

namespace App\Module\Client\Dto;

use App\Module\Client\Entity\ClientStatus;
use App\Shared\Validation\ValidationException;

/**
 * Dane klienta przyslane przez SPA, juz oczyszczone i sprawdzone.
 *
 * Kontroler dostaje surowa tablice z JSON-a; ten DTO jest granica, za ktora
 * serwis domenowy moze zalozyc, ze dane sa poprawne.
 */
final readonly class ClientInput
{
    private function __construct(
        public string $name,
        public ?string $nip,
        public ?string $email,
        public ?string $phone,
        public ?string $address,
        public ClientStatus $status,
        public ?string $notes,
    ) {}

    /**
     * @param array<string, mixed> $data
     * @throws ValidationException gdy dane nie przechodza walidacji.
     */
    public static function fromArray(array $data): self
    {
        $errors = [];

        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            $errors['name'][] = 'Nazwa klienta jest wymagana.';
        } elseif (mb_strlen($name) > 255) {
            $errors['name'][] = 'Nazwa moze miec najwyzej 255 znakow.';
        }

        $nip = self::nullableString($data['nip'] ?? null);
        if ($nip !== null) {
            // Uzytkownicy wpisuja NIP z myslnikami i spacjami - normalizujemy do cyfr.
            $nip = preg_replace('/\D/', '', $nip) ?? '';
            if (strlen($nip) !== 10) {
                $errors['nip'][] = 'NIP musi skladac sie z 10 cyfr.';
            } elseif (!self::hasValidNipChecksum($nip)) {
                $errors['nip'][] = 'Suma kontrolna NIP jest niepoprawna.';
            }
        }

        $email = self::nullableString($data['email'] ?? null);
        if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'][] = 'Adres e-mail jest niepoprawny.';
        }

        $statusValue = self::nullableString($data['status'] ?? null) ?? ClientStatus::Lead->value;
        $status = ClientStatus::tryFrom($statusValue);
        if ($status === null) {
            $errors['status'][] = 'Nieznany status klienta.';
            $status = ClientStatus::Lead;
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return new self(
            name: $name,
            nip: $nip,
            email: $email,
            phone: self::nullableString($data['phone'] ?? null),
            address: self::nullableString($data['address'] ?? null),
            status: $status,
            notes: self::nullableString($data['notes'] ?? null),
        );
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null || !is_scalar($value)) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * Standardowa suma kontrolna polskiego NIP-u - tania walidacja, ktora
     * wylapuje literowki jeszcze przed zapisem do bazy.
     */
    private static function hasValidNipChecksum(string $nip): bool
    {
        $weights = [6, 5, 7, 2, 3, 4, 5, 6, 7];
        $sum = 0;

        for ($i = 0; $i < 9; $i++) {
            $sum += $weights[$i] * (int) $nip[$i];
        }

        $checksum = $sum % 11;

        return $checksum !== 10 && $checksum === (int) $nip[9];
    }
}
