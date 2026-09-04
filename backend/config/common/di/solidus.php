<?php

declare(strict_types=1);

use App\Module\Account\Service\RefreshCookie;
use App\Module\Aml\Client\AmlApiClientInterface;
use App\Module\Aml\Client\HttpAmlApiClient;
use App\Module\Delegation\Client\DelegationApiClientInterface;
use App\Module\Delegation\Client\HttpDelegationApiClient;
use App\Module\Whistleblower\Client\HttpWhistleblowerApiClient;
use App\Module\Whistleblower\Client\WhistleblowerApiClientInterface;
use App\Shared\Auth\JwtService;
use App\Shared\Config\Env;
use App\Shared\Http\CorsMiddleware;
use App\Shared\Tenant\TenantContext;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientInterface;
use Yiisoft\Definitions\Reference;

/** @var array $params */

$jwt = $params['solidus']['jwt'];
$external = $params['solidus']['externalApi'];

return [
    // Kontekst tenanta jest singletonem w obrebie jednego zadania HTTP;
    // TenantMiddleware wypelnia go i czysci.
    TenantContext::class => TenantContext::class,

    JwtService::class => [
        'class' => JwtService::class,
        '__construct()' => [
            'secret' => $jwt['secret'],
            'algorithm' => $jwt['algorithm'],
            'issuer' => $jwt['issuer'],
            'accessTtl' => $jwt['accessTtl'],
            'refreshTtl' => $jwt['refreshTtl'],
        ],
    ],

    RefreshCookie::class => [
        'class' => RefreshCookie::class,
        '__construct()' => [
            'name' => $jwt['refreshCookieName'],
            'path' => $jwt['refreshCookiePath'],
            'ttl' => $jwt['refreshTtl'],
            // Na produkcji ciasteczko musi byc Secure. W dev przez http://localhost
            // przegladarka odrzucilaby je, wiec tam wylaczamy ten wymog.
            'secure' => Env::string('APP_ENV', 'prod') !== 'dev',
        ],
    ],

    CorsMiddleware::class => [
        'class' => CorsMiddleware::class,
        '__construct()' => [
            'allowedOrigin' => $params['solidus']['cors']['allowedOrigin'],
        ],
    ],

    ClientInterface::class => static fn() => new GuzzleClient(),

    // --- Zewnetrzne aplikacje -------------------------------------------
    // Domyslnie wpiete sa klienty HTTP. W testach podmieniamy je na
    // Fake*ApiClient, wiec zaden test nie wychodzi do sieci.
    AmlApiClientInterface::class => [
        'class' => HttpAmlApiClient::class,
        '__construct()' => [
            'httpClient' => Reference::to(ClientInterface::class),
            'baseUrl' => $external['aml']['baseUrl'],
            'apiKey' => $external['aml']['apiKey'],
            'timeout' => $external['aml']['timeout'],
        ],
    ],

    DelegationApiClientInterface::class => [
        'class' => HttpDelegationApiClient::class,
        '__construct()' => [
            'httpClient' => Reference::to(ClientInterface::class),
            'baseUrl' => $external['delegation']['baseUrl'],
            'apiKey' => $external['delegation']['apiKey'],
            'timeout' => $external['delegation']['timeout'],
        ],
    ],

    WhistleblowerApiClientInterface::class => [
        'class' => HttpWhistleblowerApiClient::class,
        '__construct()' => [
            'httpClient' => Reference::to(ClientInterface::class),
            'baseUrl' => $external['whistleblower']['baseUrl'],
            'apiKey' => $external['whistleblower']['apiKey'],
            'timeout' => $external['whistleblower']['timeout'],
        ],
    ],
];
