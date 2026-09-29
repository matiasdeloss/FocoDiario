<?php

namespace App\Http\Controllers;

use App\Enums\TipoCategoria;
use App\Enums\TipoIntervalo;
use App\Models\BloqueTiempo;
use App\Models\Contexto;
use App\Models\IntervaloEstudio;
use App\Models\SesionEstudio;
use App\Services\Hoy\ListasHoy;
use App\Services\Hoy\SemanaHoy;
use App\Services\Recomendaciones\MotorRecomendaciones;
use App\Support\Saludo;
use Illuminate\Contracts\View\View;

class HoyController extends Controller
{
    private const RECOMENDACIONES = 5;

    public function __invoke(MotorRecomendaciones $motor, SemanaHoy $semana, ListasHoy $listas): View
    {
        $ahora = now();

        return view('hoy', [
            'saludo' => Saludo::para($ahora),
            'diaSemana' => mb_strtoupper($ahora->translatedFormat('l')),
            'fechaLarga' => $ahora->translatedFormat('j \d\e F \d\e Y'),
            'horasAprovechadas' => $this->horasAprovechadas(),
            'semana' => $semana->datos(),
            'destinos' => Contexto::opciones(),
            'recomendaciones' => $motor->generar()->take(self::RECOMENDACIONES),
            ...$listas->tareas(),
            ...$listas->recordatorios(),
            'pomodorosHoy' => IntervaloEstudio::query()
                ->where('tipo', TipoIntervalo::Foco)
                ->where('completado', true)
                ->whereBetween('inicio', [$ahora->copy()->startOfDay(), $ahora->copy()->endOfDay()])
                ->count(),
            'sesionActivaId' => SesionEstudio::enCurso()->latest('iniciada_en')->value('id'),
        ]);
    }

    /** Horas de bloques productivos de hoy, con una sola consulta agregada en la base. */
    private function horasAprovechadas(): float
    {
        $bloques = BloqueTiempo::query()
            ->whereHas('categoria', fn ($consulta) => $consulta->where('tipo', TipoCategoria::Productiva))
            ->whereBetween('inicio', [today()->startOfDay(), today()->endOfDay()])
            ->get(['inicio', 'fin']);

        return round($bloques->sum(fn (BloqueTiempo $bloque) => $bloque->inicio->diffInMinutes($bloque->fin)) / 60, 1);
    }
}
