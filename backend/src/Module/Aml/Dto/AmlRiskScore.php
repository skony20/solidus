<?php

declare(strict_types=1);

namespace App\Module\Aml\Dto;

use DateTimeImmutable;

/**
 * Kontrakt odpowiedzi zewnetrznej aplikacji AML.
 *
 * Solidus NIE liczy scoringu - przyjmuje gotowy wynik i tylko go pokazuje
 * oraz cache'uje. Dzieki temu zmiana metodyki oceny ryzyka nie wymaga wdrozenia
 * Solidusa, a dane wrazliwe zostaja po stronie wyspecjalizowanej aplikacji.
 */
final readonly class AmlRiskScore
{
    public const LEVEL_LOW = 'low';
    public const LEVEL_ELEVATED = 'elevated';
    public const LEVEL_CRITICAL = 'critical';

    /**
     * @param int $score Punktacja 0-100; im wyzej, tym wieksze ryzyko.
     * @param string $level Jedna z stalych LEVEL_*.
     * @param string[] $factors Czynniki, ktore podniosly ocene - do pokazania analitykowi.
     */
    public function __construct(
        public int $clientId,
        public int $score,
        public string $level,
        public array $factors,
        public DateTimeImmutable $calculatedAt,
    ) {}

    /**
     * @param array<string, mixed> $payload Odpowiedz JSON z zewnetrznej aplikacji.
     */
    public static function fromApiPayload(array $payload): self
    {
        return new self(
            clientId: (int) ($payload['clientId'] ?? 0),
            score: (int) ($payload['score'] ?? 0),
            level: (string) ($payload['level'] ?? self::LEVEL_LOW),
            factors: array_map('strval', (array) ($payload['factors'] ?? [])),
            calculatedAt: new DateTimeImmutable((string) ($payload['calculatedAt'] ?? 'now')),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'clientId' => $this->clientId,
            'score' => $this->score,
            'level' => $this->level,
            'factors' => $this->factors,
            'calculatedAt' => $this->calculatedAt->format(DATE_ATOM),
        ];
    }
}
