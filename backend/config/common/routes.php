<?php

declare(strict_types=1);

use App\Module\Account\Controller\AuthController;
use App\Module\Account\Controller\RegistrationController;
use App\Module\Aml\Controller\AmlController;
use App\Module\Calendar\Controller\CalendarController;
use App\Module\Client\Controller\ClientController;
use App\Module\Communication\Controller\CommunicationController;
use App\Module\Delegation\Controller\DelegationController;
use App\Module\Finance\Controller\FinanceController;
use App\Module\MissionControl\Controller\MissionControlController;
use App\Module\Platform\Controller\TenantAdminController;
use App\Module\Pricing\Controller\PricingController;
use App\Module\Settings\Controller\SettingsController;
use App\Module\Team\Controller\TeamController;
use App\Module\Whistleblower\Controller\WhistleblowerController;
use App\Shared\Auth\PlatformAdminMiddleware;
use App\Shared\Tenant\TenantMiddleware;
use App\Web;
use Yiisoft\Router\Group;
use Yiisoft\Router\Route;

/**
 * Mapa adresow API Solidusa.
 *
 * Podzial jest prosty: /api/auth to trasy publiczne (nie da sie wymagac
 * tokenu od kogos, kto sie wlasnie loguje), a cala reszta /api przechodzi
 * przez TenantMiddleware, ktore ustala biuro i uzytkownika.
 */
return [
    // Strona powitalna szkieletu - zostaje jako szybki test, ze backend zyje.
    Route::get('/')
        ->action(Web\HomePage\Action::class)
        ->name('home'),

    // --- Publiczne: rejestracja i logowanie -----------------------------
    Group::create('/api/auth')
        ->routes(
            Route::post('/register')->action([RegistrationController::class, 'register'])->name('auth/register'),
            Route::post('/verify-email')->action([RegistrationController::class, 'verifyEmail'])->name('auth/verify-email'),
            Route::post('/resend-code')->action([RegistrationController::class, 'resendCode'])->name('auth/resend-code'),
            Route::post('/login')->action([AuthController::class, 'login'])->name('auth/login'),
            Route::post('/refresh')->action([AuthController::class, 'refresh'])->name('auth/refresh'),
            Route::post('/logout')->action([AuthController::class, 'logout'])->name('auth/logout'),
        ),

    // --- Publiczne: cennik strony informacyjnej --------------------------
    // Strona sprzedazowa jest widoczna dla niezalogowanych, wiec i cennik musi
    // byc. Zwracane sa wylacznie plany aktywne (patrz PricingController::index).
    Route::get('/api/pricing')->action([PricingController::class, 'index'])->name('pricing/index'),

    // --- Zarzadzanie cennikiem: wylacznie administrator calego systemu ---
    // Dwa middleware po kolei: TenantMiddleware ustala tozsamosc z tokenu,
    // PlatformAdminMiddleware sprawdza role. Kolejnosc jest istotna - drugie
    // czyta to, co wstawilo pierwsze.
    Group::create('/api/admin/pricing')
        ->middleware(TenantMiddleware::class)
        ->middleware(PlatformAdminMiddleware::class)
        ->routes(
            Route::get('')->action([PricingController::class, 'adminIndex'])->name('admin/pricing/index'),
            Route::post('')->action([PricingController::class, 'create'])->name('admin/pricing/create'),
            Route::put('/{id:\d+}')->action([PricingController::class, 'update'])->name('admin/pricing/update'),
            Route::delete('/{id:\d+}')->action([PricingController::class, 'delete'])->name('admin/pricing/delete'),
        ),

    // --- Panel operatora: przeglad biur, stan, plan, platnosci -----------
    // Tak samo chronione jak cennik - TenantMiddleware + PlatformAdminMiddleware.
    // Kontrolery i repozytoria tej grupy CELOWO nie filtruja po tenant_id -
    // to jedyne miejsce w systemie, ktore ma prawo widziec wszystkie biura
    // naraz. Patrz Module\Platform\Repository\TenantAdminRepository.
    Group::create('/api/admin/tenants')
        ->middleware(TenantMiddleware::class)
        ->middleware(PlatformAdminMiddleware::class)
        ->routes(
            Route::get('')->action([TenantAdminController::class, 'index'])->name('admin/tenants/index'),
            Route::get('/{id:\d+}')->action([TenantAdminController::class, 'view'])->name('admin/tenants/view'),
            Route::put('/{id:\d+}/status')
                ->action([TenantAdminController::class, 'updateStatus'])
                ->name('admin/tenants/update-status'),
            Route::put('/{id:\d+}/plan')
                ->action([TenantAdminController::class, 'updatePlan'])
                ->name('admin/tenants/update-plan'),
            Route::post('/{id:\d+}/payments')
                ->action([TenantAdminController::class, 'recordPayment'])
                ->name('admin/tenants/record-payment'),
        ),

    // --- Chronione: wymagaja waznego access tokenu ----------------------
    Group::create('/api')
        ->middleware(TenantMiddleware::class)
        ->routes(
            Route::get('/auth/me')->action([AuthController::class, 'me'])->name('auth/me'),

            // Modul wzorcowy - jedyny z pelnym CRUD-em.
            Group::create('/clients')->routes(
                Route::get('')->action([ClientController::class, 'index'])->name('clients/index'),
                Route::post('')->action([ClientController::class, 'create'])->name('clients/create'),
                Route::get('/{id:\d+}')->action([ClientController::class, 'view'])->name('clients/view'),
                Route::put('/{id:\d+}')->action([ClientController::class, 'update'])->name('clients/update'),
                Route::delete('/{id:\d+}')->action([ClientController::class, 'delete'])->name('clients/delete'),
            ),

            // Szkielety pozostalych modulow - jeden endpoint kontrolny kazdy.
            Route::get('/mission-control')->action([MissionControlController::class, 'index'])->name('mission-control/index'),
            Route::get('/aml')->action([AmlController::class, 'index'])->name('aml/index'),
            Route::get('/delegacje')->action([DelegationController::class, 'index'])->name('delegacje/index'),
            Route::get('/komunikacja')->action([CommunicationController::class, 'index'])->name('komunikacja/index'),
            Route::get('/kalendarz')->action([CalendarController::class, 'index'])->name('kalendarz/index'),
            Route::get('/finanse')->action([FinanceController::class, 'index'])->name('finanse/index'),
            Route::get('/zespol')->action([TeamController::class, 'index'])->name('zespol/index'),
            Route::get('/sygnalisci')->action([WhistleblowerController::class, 'index'])->name('sygnalisci/index'),
            Route::get('/ustawienia')->action([SettingsController::class, 'index'])->name('ustawienia/index'),
        ),
];
