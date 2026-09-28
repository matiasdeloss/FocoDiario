<?php

namespace App\Providers;

use App\Models\Contexto;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // La nota rápida de Hoy necesita la lista de destinos (contextos con su ruta completa).
        View::composer('hoy._nota-rapida', function ($vista) {
            $vista->with('destinos', Contexto::opciones());
        });
    }
}
