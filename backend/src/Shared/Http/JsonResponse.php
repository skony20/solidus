<?php

declare(strict_types=1);

namespace App\Shared\Http;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Http\Status;

/**
 * Jedyne miejsce, w ktorym API Solidusa zamienia dane PHP na odpowiedz HTTP w JSON.
 *
 * Dzieki temu ksztalt odpowiedzi (naglowki, kodowanie, obudowa bledu) jest
 * identyczny we wszystkich modulach.
 */
final readonly class JsonResponse
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
    ) {}

    public function ok(mixed $data): ResponseInterface
    {
        return $this->write(Status::OK, $data);
    }

    public function created(mixed $data): ResponseInterface
    {
        return $this->write(Status::CREATED, $data);
    }

    public function noContent(): ResponseInterface
    {
        return $this->responseFactory->createResponse(Status::NO_CONTENT);
    }

    /**
     * @param array<string, string[]> $details Bledy walidacji per pole.
     */
    public function error(int $status, string $message, array $details = []): ResponseInterface
    {
        $body = ['error' => ['message' => $message]];

        if ($details !== []) {
            $body['error']['details'] = $details;
        }

        return $this->write($status, $body);
    }

    public function notFound(string $message = 'Nie znaleziono zasobu.'): ResponseInterface
    {
        return $this->error(Status::NOT_FOUND, $message);
    }

    public function unauthorized(string $message = 'Wymagane uwierzytelnienie.'): ResponseInterface
    {
        return $this->error(Status::UNAUTHORIZED, $message);
    }

    public function unprocessable(string $message, array $details = []): ResponseInterface
    {
        return $this->error(Status::UNPROCESSABLE_ENTITY, $message, $details);
    }

    private function write(int $status, mixed $data): ResponseInterface
    {
        $response = $this->responseFactory->createResponse($status);
        $response->getBody()->write(
            json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}
