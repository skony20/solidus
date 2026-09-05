<?php

declare(strict_types=1);

namespace App\Module\Pricing\Entity;

use DateTimeImmutable;

/**
 * Plan abonamentowy pokazywany w cenniku na stronie informacyjnej.
 *
 * Encja nie ma traitu HasTenant - cennik jest wspolny dla calego systemu.
 * Uzasadnienie w migracji {@see \App\Module\Pricing\Migration\M250905120000CreatePricingPlans}.
 */
final class PricingPlan
{
    /**
     * @param int|null $priceMonthly Cena w GROSZACH; null = wycena indywidualna.
     * @param int|null $priceYearly  Cena w GROSZACH; null = wycena indywidualna.
     * @param string[] $features     Punkty listy, w kolejnosci wyswietlania.
     */
    public function __construct(
        public ?int $id,
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
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {}

    /**
     * @param array<string, mixed> $row
     * @param string[]             $features
     */
    public static function fromRow(array $row, array $features = []): self
    {
        return new self(
            id: (int) $row['id'],
            code: (string) $row['code'],
            name: (string) $row['name'],
            tagline: self::nullableString($row['tagline'] ?? null),
            priceMonthly: isset($row['price_monthly']) ? (int) $row['price_monthly'] : null,
            priceYearly: isset($row['price_yearly']) ? (int) $row['price_yearly'] : null,
            currency: (string) $row['currency'],
            ctaLabel: self::nullableString($row['cta_label'] ?? null),
            isFeatured: (bool) $row['is_featured'],
            isActive: (bool) $row['is_active'],
            position: (int) $row['position'],
            features: $features,
            createdAt: new DateTimeImmutable((string) $row['created_at']),
            updatedAt: new DateTimeImmutable((string) $row['updated_at']),
        );
    }

    /**
     * Ksztalt dla SPA.
     *
     * Ceny jada jako liczba groszy, a nie sformatowany napis - formatowanie
     * kwoty to sprawa interfejsu (i jego lokalizacji), nie API. Backend, ktory
     * odsyla "149,00 zl", zamyka droge do innej waluty i innego jezyka.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'tagline' => $this->tagline,
            'priceMonthly' => $this->priceMonthly,
            'priceYearly' => $this->priceYearly,
            'currency' => $this->currency,
            'ctaLabel' => $this->ctaLabel,
            'isFeatured' => $this->isFeatured,
            'isActive' => $this->isActive,
            'position' => $this->position,
            'features' => $this->features,
        ];
    }

    private static function nullableString(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : (string) $value;
    }
}
