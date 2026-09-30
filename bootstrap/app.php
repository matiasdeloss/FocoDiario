<?php

use App\Http\Middleware\CabecerasSeguridad;
use App\Http\Middleware\IdentificarInvitado;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
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
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [CabecerasSeguridad::class]);
        // El invitado tiene que existir antes de autenticar y de buscar los modelos de la URL (que se filtran por usuario).
        $middleware->prependToPriorityList(before: AuthenticatesRequests::class, prepend: IdentificarInvitado::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Un pedido HTMX sin sesión no debe pegar la pantalla de entrada dentro de una fila: se redirige la página entera.
        $exceptions->render(function (AuthenticationException $excepcion, Request $request) {
            if ($request->header('HX-Request')) {
                return response('', 401)->header('HX-Redirect', route('login'));
            }
        });
    })->create();
