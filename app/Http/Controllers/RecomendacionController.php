<?php

namespace App\Http\Controllers;

use App\Enums\CategoriaRecomendacion;
use App\Services\Recomendaciones\CatalogoRecomendaciones;
use App\Services\Recomendaciones\MotorRecomendaciones;
use App\Services\Recomendaciones\Recomendacion;
use Illuminate\Contracts\View\View;

class RecomendacionController extends Controller
{
    /** Ideas del catálogo que se muestran por categoría: la página se mantiene corta. */
    private const IDEAS_POR_CATEGORIA = 2;

    public function index(MotorRecomendaciones $motor): View
    {
        $generadas = $motor->generar()->values();

        // Lo urgente que no pertenece a ninguna categoría va arriba, con el orden de prioridad del motor.
        $paraTi = $generadas->filter(fn (Recomendacion $r) => $r->categoria === null)->values();

        // Una sección por categoría (orden del enum): primero lo personalizado, luego las ideas del día.
        // Las ideas rotan por día (semilla = fecha): no cambian al recargar.
        $fecha = now()->toDateString();
        $secciones = collect(CategoriaRecomendacion::cases())
            ->map(function (CategoriaRecomendacion $categoria) use ($generadas, $fecha) {
                return [
                    'categoria' => $categoria,
                    'personales' => $generadas->filter(fn (Recomendacion $r) => $r->categoria === $categoria)->values(),
                    'ideas' => CatalogoRecomendaciones::delDia($fecha, $categoria)->take(self::IDEAS_POR_CATEGORIA)->values(),
                ];
            })
            ->filter(fn (array $s) => $s['personales']->isNotEmpty() || $s['ideas']->isNotEmpty())
            ->values();

        return view('recomendaciones.index', [
            'paraTi' => $paraTi,
            'secciones' => $secciones,
            'segunTusDatos' => $generadas->count(),
            'ideas' => $secciones->sum(fn (array $s) => $s['ideas']->count()),
            'alertas' => $generadas->filter(fn (Recomendacion $r) => $r->tipo->value === 'alerta')->count(),
        ]);
    }
}
