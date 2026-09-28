<?php

namespace App\Http\Controllers;

use App\Enums\TipoCategoria;
use App\Models\BloqueTiempo;
use App\Models\Recordatorio;
use App\Models\Tarea;
use App\Services\Recomendaciones\MotorRecomendaciones;
use Illuminate\Contracts\View\View;

class HoyController extends Controller
{
    public function __invoke(MotorRecomendaciones $motor): View
    {
        $bloquesHoy = BloqueTiempo::with('categoria')
            ->whereDate('inicio', today())
            ->get();

        $minutosAprovechados = $bloquesHoy
            ->filter(fn (BloqueTiempo $bloque) => $bloque->categoria->tipo === TipoCategoria::Productiva)
            ->sum(fn (BloqueTiempo $bloque) => $bloque->inicio->diffInMinutes($bloque->fin));

        return view('hoy', [
            'tareasAbiertas' => Tarea::abiertas()->count(),
            'proximasTareas' => Tarea::abiertas()
                ->orderByRaw('fecha_limite is null')
                ->orderBy('fecha_limite')
                ->limit(5)
                ->get(),
            'recordatorios' => Recordatorio::pendientes()->orderBy('recordar_en')->limit(5)->get(),
            'horasAprovechadas' => round($minutosAprovechados / 60, 1),
            'recomendaciones' => $motor->generar()->take(5),
        ]);
    }
}
