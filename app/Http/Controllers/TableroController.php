<?php

namespace App\Http\Controllers;

use App\Enums\EstadoTarea;
use App\Models\ColumnaTablero;
use App\Models\Contexto;
use App\Models\Tarea;
use App\Support\ColoresDeContexto;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/** Tablero Kanban de tareas: una columna por cada columna del usuario; las de tipo completada muestran solo las más recientes. */
class TableroController extends Controller
{
    private const COMPLETADAS_EN_TABLERO = 10;

    public function index(Request $request): View
    {
        $columnasTablero = ColumnaTablero::ordenadas()->withCount('tareas')->get();

        $abiertas = Tarea::query()->with('contexto')->abiertas()
            ->orderBy('orden')
            ->orderByRaw('fecha_limite is null')
            ->orderBy('fecha_limite')
            ->orderByDesc('id')
            ->get()
            ->groupBy('columna_id');

        $columnas = $columnasTablero->map(function (ColumnaTablero $columna) use ($abiertas) {
            if (! $columna->esCompletada()) {
                return ['columna' => $columna, 'tareas' => $abiertas[$columna->id] ?? collect(), 'ocultas' => 0];
            }

            $tareas = Tarea::query()->with('contexto')->where('columna_id', $columna->id)->where('estado', EstadoTarea::Completada)
                ->orderByDesc('updated_at')->orderByDesc('id')->limit(self::COMPLETADAS_EN_TABLERO)->get();

            return ['columna' => $columna, 'tareas' => $tareas, 'ocultas' => max(0, $columna->tareas_count - $tareas->count())];
        })->values();

        return view('tablero.index', [
            'columnas' => $columnas,
            'colores' => ColoresDeContexto::delUsuario(),
            'total' => $columnas->sum(fn ($c) => $c['tareas']->count() + $c['ocultas']),
            'columnasOrden' => $columnasTablero,
            'contextosFiltro' => Contexto::opcionesPorTipo(soloConTareas: true),
            'contextos' => Contexto::opcionesPorTipo(),
            'contextoInicial' => (string) $request->query('contexto', ''),
        ]);
    }
}
