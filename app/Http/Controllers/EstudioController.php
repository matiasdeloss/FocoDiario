<?php

namespace App\Http\Controllers;

use App\Enums\EstiloEstudio;
use App\Http\Requests\FiltroEstudioRequest;
use App\Models\Contexto;
use App\Models\IntervaloEstudio;
use App\Models\SesionEstudio;
use App\Models\Tarea;
use App\Services\ResumenEstudio;
use Illuminate\Contracts\View\View;

class EstudioController extends Controller
{
    /** Temporizador. El estado de la sesión en curso vive en el navegador; la base guarda lo ya registrado. */
    public function index(): View
    {
        $sesionesHoy = SesionEstudio::with('intervalos')->whereDate('iniciada_en', today())->get();

        return view('estudio.index', [
            'tareas' => Tarea::abiertas()->orderBy('titulo')->get(['id', 'titulo']),
            'contextos' => Contexto::opciones(),
            'estilos' => EstiloEstudio::cases(),
            'presets' => EstiloEstudio::presets(),
            'limites' => config('estudio.limites'),
            'sesionActivaId' => SesionEstudio::enCurso()->latest('iniciada_en')->value('id'),
            'resumenHoy' => ResumenEstudio::deIntervalos($sesionesHoy->flatMap->intervalos),
        ]);
    }

    public function historial(FiltroEstudioRequest $request): View
    {
        $filtros = $request->validated();

        $sesiones = SesionEstudio::with(['contexto', 'tarea', 'intervalos'])
            ->filtrar($filtros)
            ->orderByDesc('iniciada_en')
            ->paginate(15)
            ->withQueryString();

        return view('estudio.historial', [
            'sesiones' => $sesiones,
            'dias' => $sesiones->getCollection()->groupBy(fn (SesionEstudio $sesion) => $sesion->iniciada_en->toDateString()),
            'totalesPorDia' => $this->totalesPorDia($sesiones->getCollection(), $filtros),
            'totales' => ResumenEstudio::deIntervalos(
                IntervaloEstudio::whereHas('sesion', fn ($consulta) => $consulta->filtrar($filtros))
                    ->get(['tipo', 'completado', 'duracion_seg'])
            ),
            'contextos' => Contexto::opciones(),
            'filtros' => $filtros,
        ]);
    }

    public function metodos(): View
    {
        return view('estudio.metodos', [
            'metodos' => config('estudio.metodos'),
            'notaEstilos' => config('estudio.nota_estilos'),
            'notaEstilosFuente' => config('estudio.nota_estilos_fuente'),
        ]);
    }

    /**
     * Totales de cada día visible, calculados con todas las sesiones de ese día
     * (aunque una parte caiga en otra página del listado).
     *
     * @return array<string, array<string, int>>
     */
    private function totalesPorDia($sesionesDePagina, array $filtros): array
    {
        if ($sesionesDePagina->isEmpty()) {
            return [];
        }

        $primero = $sesionesDePagina->min('iniciada_en')->copy()->startOfDay();
        $ultimo = $sesionesDePagina->max('iniciada_en')->copy()->endOfDay();

        return SesionEstudio::with('intervalos')
            ->filtrar($filtros)
            ->whereBetween('iniciada_en', [$primero, $ultimo])
            ->get()
            ->groupBy(fn (SesionEstudio $sesion) => $sesion->iniciada_en->toDateString())
            ->map(fn ($sesiones) => ResumenEstudio::deIntervalos($sesiones->flatMap->intervalos))
            ->all();
    }
}
