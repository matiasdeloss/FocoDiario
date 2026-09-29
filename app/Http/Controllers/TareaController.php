<?php

namespace App\Http\Controllers;

use App\Enums\EstadoTarea;
use App\Enums\PrioridadTarea;
use App\Http\Requests\CambiarEstadoTareaRequest;
use App\Http\Requests\FiltroTareasRequest;
use App\Http\Requests\TareaRequest;
use App\Models\Tarea;
use App\Services\Hoy\ListasHoy;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

class TareaController extends Controller
{
    private const COMPLETADAS_EN_TABLERO = 10;

    public function index(FiltroTareasRequest $request): View
    {
        $filtros = $request->validated();

        if (($filtros['vista'] ?? 'lista') === 'tablero') {
            return $this->tablero($filtros['proyecto'] ?? null);
        }

        $tareas = Tarea::query()
            ->when($filtros['estado'] ?? null, fn ($consulta, $estado) => $consulta->where('estado', $estado))
            ->when($filtros['proyecto'] ?? null, fn ($consulta, $proyecto) => $consulta->where('proyecto', $proyecto))
            ->orderByRaw("estado = 'completada'")
            ->orderByRaw('fecha_limite is null')
            ->orderBy('fecha_limite')
            ->orderByDesc('id')
            ->get();

        return view('tareas.index', [
            'tareas' => $tareas,
            'proyectos' => $this->proyectos(),
            'estados' => EstadoTarea::cases(),
            'estadoFiltro' => $filtros['estado'] ?? null,
            'proyectoFiltro' => $filtros['proyecto'] ?? null,
            'vista' => 'lista',
        ]);
    }

    /** Vista de tablero: una columna por estado; la de completadas muestra solo las más recientes. */
    private function tablero(?string $proyecto): View
    {
        $filtrarProyecto = fn ($consulta) => $consulta->when($proyecto, fn ($c) => $c->where('proyecto', $proyecto));

        $abiertas = $filtrarProyecto(Tarea::query()->abiertas())
            ->orderByRaw('fecha_limite is null')
            ->orderBy('fecha_limite')
            ->orderByDesc('id')
            ->get()
            ->groupBy(fn (Tarea $tarea) => $tarea->estado->value);

        $completadas = $filtrarProyecto(Tarea::query()->where('estado', EstadoTarea::Completada))
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(self::COMPLETADAS_EN_TABLERO)
            ->get();

        $totalCompletadas = $filtrarProyecto(Tarea::query()->where('estado', EstadoTarea::Completada))->count();

        $columnas = collect(EstadoTarea::cases())->map(fn (EstadoTarea $estado) => [
            'estado' => $estado,
            'tareas' => $estado === EstadoTarea::Completada ? $completadas : ($abiertas[$estado->value] ?? collect()),
            'ocultas' => $estado === EstadoTarea::Completada ? max(0, $totalCompletadas - $completadas->count()) : 0,
        ]);

        return view('tareas.index', [
            'columnas' => $columnas,
            'total' => $abiertas->flatten()->count() + $totalCompletadas,
            'proyectos' => $this->proyectos(),
            'proyectoFiltro' => $proyecto,
            'estadoFiltro' => null,
            'vista' => 'tablero',
        ]);
    }

    public function create(Request $request): View
    {
        // ?fecha=AAAA-MM-DD precarga la fecha límite (viene del calendario).
        return view('tareas.crear', $this->datosFormulario(new Tarea([
            'fecha_limite' => Carbon::hasFormat((string) $request->query('fecha'), 'Y-m-d') ? $request->query('fecha') : null,
            'prioridad' => PrioridadTarea::Media,
            'estado' => EstadoTarea::Pendiente,
        ])));
    }

    public function store(TareaRequest $request, ListasHoy $listas): RedirectResponse|JsonResponse
    {
        $tarea = Tarea::create($request->validated());

        // Alta rápida de Hoy: se responde con lo necesario para dibujar la fila sin recargar.
        if ($request->expectsJson()) {
            return response()->json([
                'id' => $tarea->id,
                'titulo' => $tarea->titulo,
                'prioridad' => $tarea->prioridad->value,
                'estado' => $tarea->estado->value,
                'html' => view('hoy._tarea', ['tarea' => $tarea])->render(),
            ] + $this->listaHoy($listas), 201);
        }

        return redirect()->route('tareas.index')->with('estado', 'Tarea creada.');
    }

    public function show(Tarea $tarea): RedirectResponse
    {
        return redirect()->route('tareas.edit', $tarea);
    }

    public function edit(Tarea $tarea): View
    {
        return view('tareas.editar', $this->datosFormulario($tarea));
    }

    public function update(TareaRequest $request, Tarea $tarea): RedirectResponse
    {
        $tarea->update($request->validated());

        return redirect()->route('tareas.index')->with('estado', 'Tarea actualizada.');
    }

    public function destroy(Request $request, Tarea $tarea): Response|RedirectResponse
    {
        $tarea->delete();

        if ($request->header('HX-Request')) {
            return response('');
        }

        return redirect()->route('tareas.index')->with('estado', 'Tarea eliminada.');
    }

    /** Cambia el estado con un clic. Con HTMX devuelve solo la fila actualizada. */
    public function cambiarEstado(CambiarEstadoTareaRequest $request, Tarea $tarea, ListasHoy $listas): View|RedirectResponse|JsonResponse
    {
        $tarea->update($request->validated());

        if ($request->expectsJson()) {
            return response()->json(['id' => $tarea->id, 'estado' => $tarea->estado->value] + $this->listaHoy($listas));
        }

        if ($request->header('HX-Request')) {
            return view('tareas._fila', ['tarea' => $tarea]);
        }

        return back()->with('estado', 'Estado actualizado.');
    }

    /**
     * Estado de la tarjeta Tareas abiertas de Hoy tal como queda en la base: la lista ya ordenada
     * (abiertas por prioridad, últimas completadas al final) y el total de pendientes.
     * Hoy lo toma como fuente de verdad tras cada cambio.
     *
     * @return array{lista: string, pendientes: int}
     */
    private function listaHoy(ListasHoy $listas): array
    {
        $datos = $listas->tareas();

        return [
            'lista' => view('hoy._tareas-lista', $datos)->render(),
            'pendientes' => $datos['totalPendientes'],
        ];
    }

    private function proyectos()
    {
        return Tarea::whereNotNull('proyecto')->distinct()->orderBy('proyecto')->pluck('proyecto');
    }

    private function datosFormulario(Tarea $tarea): array
    {
        return [
            'tarea' => $tarea,
            'prioridades' => PrioridadTarea::cases(),
            'estados' => EstadoTarea::cases(),
            'proyectos' => $this->proyectos(),
        ];
    }
}
