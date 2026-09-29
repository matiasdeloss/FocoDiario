<?php

namespace App\Http\Controllers;

use App\Enums\EstiloEstudio;
use App\Enums\TipoIntervalo;
use App\Http\Requests\FiltroEstudioRequest;
use App\Models\Contexto;
use App\Models\IntervaloEstudio;
use App\Models\SesionEstudio;
use App\Models\Tarea;
use App\Services\ResumenEstudio;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class EstudioController extends Controller
{
    /** Temporizador. El estado de la sesión en curso vive en el navegador; la base guarda lo ya registrado. */
    public function index(Request $request): View
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
        ] + $this->datosHistorialResumido($request));
    }

    /**
     * Historial compacto que se muestra debajo del temporizador: rango (semana o mes),
     * filtro por materia, totales, racha, minutos de foco por día y sesiones agrupadas por día.
     *
     * @return array<string, mixed>
     */
    private function datosHistorialResumido(Request $request): array
    {
        $rango = $request->query('rango') === 'mes' ? 'mes' : 'semana';
        $contextoId = ctype_digit((string) $request->query('materia')) ? (int) $request->query('materia') : null;
        $hoy = CarbonImmutable::today();
        $desde = $rango === 'mes' ? $hoy->startOfMonth() : $hoy->startOfWeek();
        $hasta = $rango === 'mes' ? $hoy->endOfMonth() : $hoy->endOfWeek();
        $filtros = ['contexto_id' => $contextoId];

        $sesiones = SesionEstudio::with(['contexto', 'tarea', 'intervalos'])
            ->filtrar($filtros)
            ->whereBetween('iniciada_en', [$desde->startOfDay(), $hasta->endOfDay()])
            ->orderByDesc('iniciada_en')
            ->get();

        $porDia = $sesiones->groupBy(fn (SesionEstudio $s) => $s->iniciada_en->toDateString());
        $barras = [];
        for ($dia = $desde; $dia <= $hasta; $dia = $dia->addDay()) {
            $clave = $dia->toDateString();
            $seg = (int) ($porDia[$clave] ?? collect())->sum(fn (SesionEstudio $s) => $s->segundosDe(TipoIntervalo::Foco));
            $barras[] = ['fecha' => $dia, 'min' => intdiv($seg, 60), 'seg' => $seg, 'futuro' => $dia > $hoy];
        }

        $diasConFoco = SesionEstudio::whereHas('intervalos', fn ($q) => $q->where('tipo', TipoIntervalo::Foco)->where('completado', true))
            ->where('iniciada_en', '>=', $hoy->subDays(400))
            ->pluck('iniciada_en')
            ->map(fn ($f) => $f->toDateString())
            ->unique()
            ->flip();
        $racha = 0;
        $cursor = isset($diasConFoco[$hoy->toDateString()]) ? $hoy : $hoy->subDay();
        while (isset($diasConFoco[$cursor->toDateString()])) {
            $racha++;
            $cursor = $cursor->subDay();
        }

        return [
            'hRango' => $rango,
            'hMateria' => $contextoId,
            'hDesde' => $desde,
            'hDias' => $porDia,
            'hBarras' => $barras,
            'hTotales' => ResumenEstudio::deIntervalos($sesiones->flatMap->intervalos),
            'hSesiones' => $sesiones->count(),
            'hRacha' => $racha,
        ];
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
