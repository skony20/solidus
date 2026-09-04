<?php

declare(strict_types=1);

namespace App\Module\Whistleblower\Client;

use App\Module\Whistleblower\Dto\WhistleblowerCase;
use App\Shared\ExternalApi\ExternalApiException;

/**
 * Kontrakt rozmowy z zewnetrzna aplikacja kanalu sygnalistow.
 *
 * Interfejs celowo nie ma metody zwracajacej tresc zgloszenia - Solidus
 * operuje wylacznie na metadanych.
 */
interface WhistleblowerApiClientInterface
{
    /**
     * @return WhistleblowerCase[]
     * @throws ExternalApiException
     */
    public function openCases(int $tenantId): array;

    /**
     * @throws ExternalApiException
     */
    public function caseMetadata(int $tenantId, string $caseId): WhistleblowerCase;

    /**
     * Liczba zgloszen po terminie reakcji - wskaznik na pulpit.
     *
     * @throws ExternalApiException
     */
    public function overdueCount(int $tenantId): int;
}
