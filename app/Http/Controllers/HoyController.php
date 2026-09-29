<?php

namespace App\Http\Controllers;

use App\Enums\EstadoTarea;
use App\Enums\TipoCategoria;
use App\Enums\TipoIntervalo;
use App\Models\BloqueTiempo;
use App\Models\Contexto;
use App\Models\IntervaloEstudio;
use App\Models\Recordatorio;
use App\Models\SesionEstudio;
use App\Models\Tarea;
use App\Services\Hoy\SemanaHoy;
use App\Services\Recomendaciones\MotorRecomendaciones;
use App\Support\Saludo;
use Illuminate\Contracts\View\View;

class HoyController extends Controller
{
    private const TAREAS_ABIERTAS = 8;

    private const TAREAS_COMPLETADAS = 3;

    private const RECORDATORIOS = 5;

    private const RECOMENDACIONES = 5;

    public function __invoke(MotorRecomendaciones $motor, SemanaHoy $semana): View
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
            'tareasAbiertas' => $this->tareasAbiertas(),
            'tareasCompletadas' => Tarea::where('estado', EstadoTarea::Completada)
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->limit(self::TAREAS_COMPLETADAS)
                ->get(),
            'totalPendientes' => Tarea::abiertas()->count(),
            'recordatorios' => Recordatorio::pendientes()->conFecha()->orderBy('recordar_en')->limit(self::RECORDATORIOS)->get(),
            'recordatoriosSinFecha' => Recordatorio::pendientes()->sinFecha()->count(),
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

    /** Abiertas por prioridad (alta primero) y fecha límite. */
    private function tareasAbiertas()
    {
        return Tarea::abiertas()
            ->orderByRaw("case prioridad when 'alta' then 0 when 'media' then 1 else 2 end")
            ->orderByRaw('fecha_limite is null')
            ->orderBy('fecha_limite')
            ->orderBy('id')
            ->limit(self::TAREAS_ABIERTAS)
            ->get();
    }
}
