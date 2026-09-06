<?php

declare(strict_types=1);

use App\Shared\ApplicationParams;
use App\Shared\Config\Env;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Assets\AssetManager;
use Yiisoft\Definitions\Reference;
use Yiisoft\Router\CurrentRoute;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Yii\View\Renderer\CsrfViewInjection;

return [
    'application' => require __DIR__ . '/application.php',

    'yiisoft/aliases' => [
        'aliases' => require __DIR__ . '/aliases.php',
    ],

    'yiisoft/view' => [
        'basePath' => null,
        'parameters' => [
            'assetManager' => Reference::to(AssetManager::class),
            'applicationParams' => Reference::to(ApplicationParams::class),
            'aliases' => Reference::to(Aliases::class),
            'urlGenerator' => Reference::to(UrlGeneratorInterface::class),
            'currentRoute' => Reference::to(CurrentRoute::class),
        ],
    ],

    'yiisoft/yii-view-renderer' => [
        'viewPath' => null,
        'layout' => '@src/Web/Shared/Layout/Main/layout.php',
        'injections' => [
            Reference::to(CsrfViewInjection::class),
        ],
    ],

    // Migracje mieszkaja przy modulach, ktorych dotycza.
    'yiisoft/db-migration' => [
        'newMigrationNamespace' => 'App\Shared\Migration',
        'newMigrationPath' => dirname(__DIR__, 2) . '/src/Shared/Migration',
        'sourceNamespaces' => [
            'App\Shared\Migration',
            'App\Module\Account\Migration',
            'App\Module\Client\Migration',
            'App\Module\Pricing\Migration',
            'App\Module\Platform\Migration',
        ],
        'sourcePaths' => [],
    ],

    // ---------------------------------------------------------------
    // Parametry Solidusa
    // ---------------------------------------------------------------
    'solidus' => [
        'db' => [
            'host' => Env::string('DB_HOST', '127.0.0.1'),
            'port' => Env::string('DB_PORT', '3306'),
            'name' => Env::string('DB_NAME', 'solidus'),
            'user' => Env::string('DB_USER', 'solidus'),
            'password' => Env::string('DB_PASSWORD', 'solidus'),
            'charset' => 'utf8mb4',
        ],

        // Redis i kolejka zadan sa na razie wylaczone - srodowisko docelowe
        // nie ma Redisa. Zadania, ktore mialy trafic do kolejki (masowe
        // wysylki e-mail, odswiezanie danych AML), wykonuja sie synchronicznie
        // albo czekaja na moment, gdy Redis bedzie dostepny.
        // Przywrocenie: patrz docs/ARCHITECTURE.md, sekcja "Kolejka".

        'jwt' => [
            // HS256; sekret MUSI byc nadpisany na produkcji przez zmienna srodowiskowa.
            'secret' => Env::string('JWT_SECRET', ''),
            'algorithm' => 'HS256',
            'issuer' => Env::string('JWT_ISSUER', 'solidus'),
            // Access token: 15 minut. Refresh token: 30 dni.
            'accessTtl' => Env::int('JWT_ACCESS_TTL', 900),
            'refreshTtl' => Env::int('JWT_REFRESH_TTL', 2_592_000),
            'refreshCookieName' => 'solidus_refresh',
            'refreshCookiePath' => '/api/auth',
        ],

        'cors' => [
            'allowedOrigin' => Env::string('FRONTEND_ORIGIN', 'http://localhost:5173'),
        ],

        // Poczta wychodzaca. DSN w formacie Symfony Mailer - w dev kieruje na
        // Mailhog (nic nie wychodzi na zewnatrz), na produkcji na prawdziwy
        // serwer SMTP. Adres nadawcy jest jeden dla calego systemu.
        'mail' => [
            'dsn' => Env::string('MAILER_DSN', 'smtp://mailhog:1025'),
            'fromEmail' => Env::string('MAILER_FROM', 'no-reply@solidus.local'),
            'fromName' => Env::string('MAILER_FROM_NAME', 'Solidus'),
        ],

        // ---------------------------------------------------------------
        // Zewnetrzne aplikacje. Solidus tylko z nimi rozmawia - nie liczy
        // scoringu AML, nie prowadzi delegacji, nie obsluguje zgloszen
        // sygnalistow. Wartosci do uzupelnienia, gdy te aplikacje beda gotowe.
        // ---------------------------------------------------------------
        'externalApi' => [
            'aml' => [
                'baseUrl' => Env::string('AML_API_URL', ''),        // do uzupelnienia
                'apiKey' => Env::string('AML_API_KEY', ''),          // do uzupelnienia
                'timeout' => Env::int('AML_API_TIMEOUT', 10),
            ],
            'delegation' => [
                'baseUrl' => Env::string('DELEGO_API_URL', ''),      // do uzupelnienia (DelegoApp)
                'apiKey' => Env::string('DELEGO_API_KEY', ''),       // do uzupelnienia
                'timeout' => Env::int('DELEGO_API_TIMEOUT', 10),
            ],
            'whistleblower' => [
                'baseUrl' => Env::string('WHISTLEBLOWER_API_URL', ''), // do uzupelnienia
                'apiKey' => Env::string('WHISTLEBLOWER_API_KEY', ''),  // do uzupelnienia
                'timeout' => Env::int('WHISTLEBLOWER_API_TIMEOUT', 10),
            ],
        ],
    ],
];
