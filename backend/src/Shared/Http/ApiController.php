<?php

declare(strict_types=1);

namespace App\Shared\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Baza dla kontrolerow API. Daje potomkom gotowa fabryke odpowiedzi JSON
 * i odczyt ciala zadania, zeby kazdy kontroler nie powtarzal tego samego.
 */
abstract readonly class ApiController
{
    public function __construct(
        protected JsonResponse $json,
    ) {}

    /**
     * Odczytuje cialo zadania jako tablice. Puste lub niepoprawne cialo daje [].
     *
     * @return array<string, mixed>
     */
    protected function body(ServerRequestInterface $request): array
    {
        $parsed = $request->getParsedBody();
        if (is_array($parsed) && $parsed !== []) {
            return $parsed;
        }

        $raw = (string) $request->getBody();
        if ($raw === '') {
            return [];
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Standardowa odpowiedz szkieletu modulu, ktory nie ma jeszcze logiki.
     */
    protected function modulePlaceholder(string $module): ResponseInterface
    {
        return $this->json->ok(['status' => 'ok', 'module' => $module]);
    }
}
