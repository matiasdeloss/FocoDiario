<?php

namespace App\Providers;

use App\Models\ColumnaTablero;
use App\Models\Contexto;
use App\Models\Tarea;
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

        // El formulario de captura (también cuando HTMX lo devuelve solo) ofrece los campos propios de la tarea:
        // proyectos en uso y columnas del tablero, una consulta cada uno por render.
        View::composer('hoy._captura-form', function ($vista) {
            $datos = $vista->getData();
            $vista->with([
                'proyectos' => $datos['proyectos'] ?? Tarea::proyectos(),
                'columnasOrden' => $datos['columnasOrden'] ?? ColumnaTablero::ordenadas()->get(),
            ]);
        });
    }
}
