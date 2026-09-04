<?php

declare(strict_types=1);

namespace App\Module\Whistleblower\Dto;

use DateTimeImmutable;

/**
 * Kontrakt zgloszenia sygnalisty z zewnetrznej aplikacji.
 *
 * Tresc zgloszenia CELOWO nie trafia do bazy Solidusa. Ustawa o ochronie
 * sygnalistow wymaga scislej kontroli dostepu i anonimowosci, wiec kanal
 * zgloszen zyje w osobnej aplikacji, a Solidus widzi tylko metadane:
 * ze zgloszenie istnieje, w jakim jest stanie i kiedy mija termin reakcji.
 */
final readonly class WhistleblowerCase
{
    public const STATUS_NEW = 'new';
    public const STATUS_IN_REVIEW = 'in_review';
    public const STATUS_CLOSED = 'closed';

    public function __construct(
        public string $caseId,
        public string $status,
        public string $category,
        public bool $isAnonymous,
        public DateTimeImmutable $submittedAt,
        public ?DateTimeImmutable $responseDeadline,
    ) {}

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromApiPayload(array $payload): self
    {
        $deadline = $payload['responseDeadline'] ?? null;

        return new self(
            caseId: (string) ($payload['caseId'] ?? ''),
            status: (string) ($payload['status'] ?? self::STATUS_NEW),
            category: (string) ($payload['category'] ?? 'other'),
            isAnonymous: (bool) ($payload['isAnonymous'] ?? true),
            submittedAt: new DateTimeImmutable((string) ($payload['submittedAt'] ?? 'now')),
            responseDeadline: is_string($deadline) ? new DateTimeImmutable($deadline) : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'caseId' => $this->caseId,
            'status' => $this->status,
            'category' => $this->category,
            'isAnonymous' => $this->isAnonymous,
            'submittedAt' => $this->submittedAt->format(DATE_ATOM),
            'responseDeadline' => $this->responseDeadline?->format(DATE_ATOM),
        ];
    }
}
