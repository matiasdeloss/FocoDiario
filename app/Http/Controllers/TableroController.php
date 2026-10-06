<?php

namespace App\Http\Controllers;

use App\Models\ColumnaTablero;
use App\Models\Contexto;
use App\Models\Nota;
use App\Models\Tablero;
use App\Models\Tarea;
use App\Support\ColoresDeContexto;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Tablero Kanban del usuario: varios tableros (se elige con ?tablero=, y se recuerda el último), una columna por cada
 * columna del tablero elegido y, en cada una, tareas y notas juntas. Las columnas de tipo completada muestran solo las más recientes.
 */
class TableroController extends Controller
{
    private const COMPLETADAS_EN_TABLERO = 10;

    public function index(Request $request): View|RedirectResponse
    {
        // Enlaces viejos ?proyecto=NOMBRE: van al contexto con ese nombre (si no existe, se ignora).
        if (! $request->filled('contexto') && ($contextoId = Contexto::idPorNombre($request->query('proyecto'))) !== null) {
            return redirect()->route('tablero.index', ['contexto' => $contextoId] + $request->except('proyecto'));
        }

        $tableros = Tablero::ordenados()->get();
        $actual = $this->elegido($request, $tableros);

        $columnasTablero = ColumnaTablero::ordenadas()->where('tablero_id', $actual->id)->withCount(['tareas', 'notas'])->get();
        $idsAbiertas = $columnasTablero->reject->esCompletada()->pluck('id');

        // Todas las tarjetas de las columnas abiertas, en dos consultas (tareas y notas); las completadas se piden aparte y acotadas.
        $tareasAbiertas = Tarea::query()->with(['contexto', 'notas'])->whereIn('columna_id', $idsAbiertas)->get()->groupBy('columna_id');
        $notasAbiertas = Nota::query()->with('contexto')->whereIn('columna_id', $idsAbiertas)->get()->groupBy('columna_id');

        $columnas = $columnasTablero->map(function (ColumnaTablero $columna) use ($tareasAbiertas, $notasAbiertas) {
            $total = $columna->tareas_count + $columna->notas_count;

            if (! $columna->esCompletada()) {
                $tareas = $tareasAbiertas[$columna->id] ?? collect();
                $notas = $notasAbiertas[$columna->id] ?? collect();

                $tarjetas = $this->ordenar($tareas, $notas);

                return ['columna' => $columna, 'tareas' => $tarjetas->whereInstanceOf(Tarea::class)->values(), 'tarjetas' => $tarjetas, 'ocultas' => 0];
            }

            $tareas = Tarea::query()->with(['contexto', 'notas'])->where('columna_id', $columna->id)
                ->orderByDesc('updated_at')->orderByDesc('id')->limit(self::COMPLETADAS_EN_TABLERO)->get();
            $notas = Nota::query()->with('contexto')->where('columna_id', $columna->id)
                ->orderByDesc('updated_at')->orderByDesc('id')->limit(self::COMPLETADAS_EN_TABLERO)->get();

            // Las más recientes entre tareas y notas; el resto queda fuera ("ver todas").
            $recientes = $tareas->concat($notas)->sortByDesc(fn ($t) => $t->updated_at)->take(self::COMPLETADAS_EN_TABLERO)->values();

            return [
                'columna' => $columna,
                'tareas' => $recientes->whereInstanceOf(Tarea::class)->values(),
                'tarjetas' => $recientes,
                'ocultas' => max(0, $total - $recientes->count()),
            ];
        })->values();

        // Los contextos se cargan una sola vez para los colores y las dos listas de opciones.
        $contextosDelUsuario = Contexto::todos();

        return view('tablero.index', [
            'columnas' => $columnas,
            'total' => $columnas->sum(fn ($c) => $c['tarjetas']->count() + $c['ocultas']),
            'columnasOrden' => $columnasTablero,
            'colores' => ColoresDeContexto::desde($contextosDelUsuario),
            'contextosFiltro' => Contexto::opcionesPorTipo(soloConTareas: true, contextos: $contextosDelUsuario),
            'contextos' => Contexto::opcionesPorTipo(contextos: $contextosDelUsuario),
            'contextoInicial' => (string) $request->query('contexto', ''),
            'tableros' => $tableros,
            'tableroActual' => $actual,
            'destinos' => Contexto::opciones($contextosDelUsuario),
            'columnasTodas' => ColumnaTablero::paraSelector(),
        ]);
    }

    /** El tablero de ?tablero=, o el último que se miró, o el principal. */
    private function elegido(Request $request, Collection $tableros): Tablero
    {
        $pedido = (int) $request->query('tablero', 0) ?: (int) $request->session()->get('tablero_actual', 0);
        $actual = $tableros->firstWhere('id', $pedido) ?? $tableros->firstWhere('principal', true) ?? $tableros->first();

        abort_if($actual === null, 404);
        $request->session()->put('tablero_actual', $actual->id);

        return $actual;
    }

    /**
     * Tareas y notas de una columna juntas, por el orden manual y, a igual orden, las tareas con fecha antes que las
     * demás y lo más nuevo primero.
     *
     * @return Collection<int, Tarea|Nota>
     */
    private function ordenar(Collection $tareas, Collection $notas): Collection
    {
        return $tareas->concat($notas)->sort(function ($a, $b) {
            $clave = fn ($t) => [$t->orden, $t instanceof Tarea && $t->fecha_limite !== null ? $t->fecha_limite->toDateString() : '9999-12-31', -$t->id];

            return $clave($a) <=> $clave($b);
        })->values();
    }
}
