<?php

declare(strict_types=1);

namespace App\Module\Platform\Entity;

use App\Module\Account\Entity\Tenant;
use DateTimeImmutable;

/**
 * Biuro widziane oczami operatora - {@see Tenant} plus to, czego nie widzi
 * biuro samo o sobie: nazwa planu z katalogu, liczba kont pracowniczych
 * i data, do ktorej abonament jest oplacony.
 *
 * CELOWO nie ma tu ani jednego pola merytorycznego biura (klienci, oceny
 * ryzyka, dokumentacja AML) - panel operatora pokazuje, ZE biuro istnieje
 * i placi, a nie CO biuro robi. Patrz docs/ARCHITECTURE.md sekcja 2.11.
 */
final readonly class TenantOverview
{
    public function __construct(
        public Tenant $tenant,
        public ?string $planCode,
        public ?string $planName,
        public int $userCount,
        /** Koniec ostatniego oplaconego okresu; null = nigdy nie zaksiegowano platnosci. */
        public ?DateTimeImmutable $paidUntil,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $today = new DateTimeImmutable('today');

        return [
            ...$this->tenant->toAdminArray(),
            'planCode' => $this->planCode,
            'planName' => $this->planName,
            'userCount' => $this->userCount,
            'paidUntil' => $this->paidUntil?->format('Y-m-d'),
            // Rozroznienie miedzy "nigdy nie placilo" (null) a "przeterminowane"
            // (data w przeszlosci) - to dwa rozne sygnaly alarmowe dla operatora.
            'isPaidUpToDate' => $this->paidUntil !== null && $this->paidUntil >= $today,
        ];
    }
}
