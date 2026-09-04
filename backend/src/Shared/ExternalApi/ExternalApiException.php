<?php

declare(strict_types=1);

namespace App\Shared\ExternalApi;

use RuntimeException;

/**
 * Zewnetrzna aplikacja (AML, DelegoApp, Sygnalisci) nie odpowiedziala
 * albo odpowiedziala bledem.
 *
 * Wspolny typ dla wszystkich trzech integracji i celowo oddzielony od
 * wyjatkow Guzzle - warstwa domenowa nie powinna wiedziec, jaka biblioteka
 * HTTP jest pod spodem.
 */
final class ExternalApiException extends RuntimeException
{
}
