<?php

declare(strict_types=1);

use App\Shared\Http\CorsMiddleware;
use App\Web\NotFound\NotFoundHandler;
use Yiisoft\Definitions\DynamicReference;
use Yiisoft\Definitions\Reference;
use Yiisoft\ErrorHandler\Middleware\ErrorCatcher;
use Yiisoft\Input\Http\HydratorAttributeParametersResolver;
use Yiisoft\Input\Http\RequestInputParametersResolver;
use Yiisoft\Middleware\Dispatcher\CompositeParametersResolver;
use Yiisoft\Middleware\Dispatcher\MiddlewareDispatcher;
use Yiisoft\Middleware\Dispatcher\ParametersResolverInterface;
use Yiisoft\RequestProvider\RequestCatcherMiddleware;
use Yiisoft\Router\Middleware\Router;
use Yiisoft\Session\SessionMiddleware;
use Yiisoft\Yii\Http\Application;

/** @var array $params */

return [
    Application::class => [
        '__construct()' => [
            'dispatcher' => DynamicReference::to([
                'class' => MiddlewareDispatcher::class,
                'withMiddlewares()' => [
                    [
                        ErrorCatcher::class,
                        // CORS musi stac PRZED routerem. Zapytanie kontrolne
                        // OPTIONS nie pasuje do zadnej naszej trasy (mamy tylko
                        // GET/POST/PUT/DELETE), wiec router odpowiedzialby na nie
                        // sam - bez naglowkow CORS - i przegladarka zablokowalaby
                        // wlasciwe zadanie, zanim dotarloby do aplikacji.
                        CorsMiddleware::class,
                        SessionMiddleware::class,
                        // CsrfTokenMiddleware ze szkieletu zostal tu celowo
                        // pominiety. Chroni on formularze uwierzytelniane
                        // ciasteczkiem sesji, a API Solidusa jest bezstanowe:
                        // tozsamosc jedzie w naglowku Authorization, ktorego
                        // przegladarka nie doklei automatycznie do zadania
                        // z obcej strony. Ciasteczko refresh tokenu jest
                        // dodatkowo SameSite=Strict i ograniczone do
                        // /api/auth, wiec token CSRF nic by tu nie dodal,
                        // a blokowalby kazdy POST z SPA.
                        RequestCatcherMiddleware::class,
                        Router::class,
                    ],
                ],
            ]),
            'fallbackHandler' => Reference::to(NotFoundHandler::class),
        ],
    ],

    ParametersResolverInterface::class => [
        'class' => CompositeParametersResolver::class,
        '__construct()' => [
            Reference::to(HydratorAttributeParametersResolver::class),
            Reference::to(RequestInputParametersResolver::class),
        ],
    ],
];
