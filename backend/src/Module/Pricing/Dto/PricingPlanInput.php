<?php

declare(strict_types=1);

namespace App\Module\Pricing\Dto;

use App\Shared\Validation\ValidationException;

/**
 * Dane planu przyslane przez panel administratora, juz sprawdzone.
 *
 * Granica zaufania: za tym DTO serwis moze zalozyc, ze kod jest poprawny,
 * ceny sa nieujemnymi liczbami groszy, a lista punktow nie zawiera pustych
 * napisow.
 */
final readonly class PricingPlanInput
{
    /**
     * @param string[] $features
     */
    public function __construct(
        public string $code,
        public string $name,
        public ?string $tagline,
        public ?int $priceMonthly,
        public ?int $priceYearly,
        public string $currency,
        public ?string $ctaLabel,
        public bool $isFeatured,
        public bool $isActive,
        public int $position,
        public array $features,
    ) {}

    /**
     * @param array<string, mixed> $data
     *
     * @throws ValidationException gdy dane nie przechodza walidacji.
     */
    public static function fromArray(array $data): self
    {
        $errors = [];

        $code = mb_strtolower(trim((string) ($data['code'] ?? '')));
        // Kod trafia do adresow i do przyszlej integracji z platnosciami,
        // wiec dopuszczamy tylko znaki bezpieczne w obu miejscach.
        if (preg_match('/^[a-z0-9][a-z0-9\-]{1,39}$/', $code) !== 1) {
            $errors['code'][] = 'Kod moze zawierac tylko male litery, cyfry i myslnik (2-40 znakow).';
        }

        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 80) {
            $errors['name'][] = 'Nazwa jest wymagana i moze miec najwyzej 80 znakow.';
        }

        $tagline = self::nullableText($data['tagline'] ?? null, 160);
        if ($tagline === false) {
            $errors['tagline'][] = 'Podtytul moze miec najwyzej 160 znakow.';
        }

        $ctaLabel = self::nullableText($data['ctaLabel'] ?? null, 60);
        if ($ctaLabel === false) {
            $errors['ctaLabel'][] = 'Etykieta przycisku moze miec najwyzej 60 znakow.';
        }

        $priceMonthly = self::price($data['priceMonthly'] ?? null);
        if ($priceMonthly === false) {
            $errors['priceMonthly'][] = 'Cena musi byc nieujemna liczba groszy albo pusta (wycena indywidualna).';
        }

        $priceYearly = self::price($data['priceYearly'] ?? null);
        if ($priceYearly === false) {
            $errors['priceYearly'][] = 'Cena musi byc nieujemna liczba groszy albo pusta (wycena indywidualna).';
        }

        $currency = mb_strtoupper(trim((string) ($data['currency'] ?? 'PLN')));
        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            $errors['currency'][] = 'Waluta musi byc trzyliterowym kodem ISO, np. PLN.';
        }

        $features = [];
        foreach ((array) ($data['features'] ?? []) as $feature) {
            $text = trim((string) $feature);
            if ($text === '') {
                // Puste pole to zwykle nieuzyty wiersz formularza, a nie blad -
                // po cichu je pomijamy zamiast blokowac zapis.
                continue;
            }
            if (mb_strlen($text) > 200) {
                $errors['features'][] = 'Pojedynczy punkt moze miec najwyzej 200 znakow.';
                continue;
            }
            $features[] = $text;
        }

        $position = (int) ($data['position'] ?? 0);
        if ($position < 0 || $position > 9999) {
            $errors['position'][] = 'Kolejnosc musi miescic sie w zakresie 0-9999.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return new self(
            code: $code,
            name: $name,
            tagline: $tagline === false ? null : $tagline,
            priceMonthly: $priceMonthly === false ? null : $priceMonthly,
            priceYearly: $priceYearly === false ? null : $priceYearly,
            currency: $currency,
            ctaLabel: $ctaLabel === false ? null : $ctaLabel,
            isFeatured: (bool) ($data['isFeatured'] ?? false),
            isActive: (bool) ($data['isActive'] ?? true),
            position: $position,
            features: $features,
        );
    }

    /**
     * @return int|false|null false oznacza blad walidacji, null - brak ceny.
     */
    private static function price(mixed $value): int|false|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            return false;
        }

        $grosze = (int) $value;

        return $grosze < 0 ? false : $grosze;
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
