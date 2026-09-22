<?php

use App\Core\Tenancy\Console\Commands\BackfillLegacyTenantUsersCommand;
use App\Core\Tenancy\Console\Commands\SeedTestStudioCommand;
use App\Core\Tenancy\Console\Commands\VerifyTenantConnectionCommand;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        VerifyTenantConnectionCommand::class,
        SeedTestStudioCommand::class,
        BackfillLegacyTenantUsersCommand::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        // Gruppo 'web' esplicito (non append/prepend): RestoreTenantConnection
        // deve girare dopo StartSession (legge session('tenant_id')) ma
        // PRIMA di SubstituteBindings (altrimenti un model binding implicito
        // risolverebbe ancora sulla connessione sbagliata) — un punto
        // intermedio che append()/prepend() da soli non permettono di
        // esprimere, quindi qui ridichiariamo l'intero gruppo di default
        // (stesso identico contenuto Laravel) con la nuova voce inserita
        // nella posizione corretta.
        $middleware->group('web', [
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
            \App\Core\Tenancy\Middleware\RestoreTenantConnection::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'tenant' => \App\Core\Tenancy\Middleware\IdentifyTenant::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
