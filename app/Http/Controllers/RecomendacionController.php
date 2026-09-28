<?php

namespace App\Http\Controllers;

use App\Enums\TipoRecomendacion;
use App\Services\Recomendaciones\MotorRecomendaciones;
use App\Services\Recomendaciones\Recomendacion;
use Illuminate\Contracts\View\View;

class RecomendacionController extends Controller
{
    public function index(MotorRecomendaciones $motor): View
    {
        $porTipo = $motor->generar()->groupBy(fn (Recomendacion $recomendacion) => $recomendacion->tipo->value);

        // Se muestran en un orden fijo: primero lo urgente y al final lo informativo.
        $grupos = collect(TipoRecomendacion::cases())
            ->filter(fn (TipoRecomendacion $tipo) => $porTipo->has($tipo->value))
            ->mapWithKeys(fn (TipoRecomendacion $tipo) => [$tipo->value => ['tipo' => $tipo, 'items' => $porTipo[$tipo->value]]]);

        return view('recomendaciones.index', ['grupos' => $grupos]);
    }
}
