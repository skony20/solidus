<?php

declare(strict_types=1);

namespace App\Shared\Http;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Yiisoft\Http\Method;
use Yiisoft\Http\Status;

/**
 * SPA dziala na innym porcie niz API (5173 vs 8080), wiec przegladarka traktuje
 * je jako inne zrodlo. Bez tego middleware refresh token w ciasteczku nigdy by
 * nie dotarl do /api/auth/refresh.
 */
final readonly class CorsMiddleware implements MiddlewareInterface
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private string $allowedOrigin,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $request->getMethod() === Method::OPTIONS
            ? $this->responseFactory->createResponse(Status::NO_CONTENT)
            : $handler->handle($request);

        return $response
            ->withHeader('Access-Control-Allow-Origin', $this->allowedOrigin)
            ->withHeader('Access-Control-Allow-Credentials', 'true')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
            ->withHeader('Vary', 'Origin');
    }
}
