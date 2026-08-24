<?php
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'module' => \App\Http\Middleware\CheckModuleEnabled::class,
        ]);
        $middleware->prependToGroup('api', \App\Http\Middleware\ApiKeyAuthentication::class);

        // Appka je dostupná jak přes vnitřní IP, tak přes doménu za
        // Nginx Proxy Managerem (podsíť 192.168.217.0/24) - bez tohohle
        // appka nepozná, že požadavek prošel přes proxy, a nesprávně by
        // si spočítala skutečnou adresu/schéma (rozbité podepsané URL,
        // např. Livewire upload souborů → chyba 422).
        $middleware->trustProxies(
            at: ['192.168.217.0/24'],
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
        );

        // Přenastaví generování URL podle skutečného hostitele každého
        // požadavku (viz komentář v samotném middleware).
        $middleware->prepend(\App\Http\Middleware\DynamicAppUrl::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
