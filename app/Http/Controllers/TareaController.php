<?php

namespace App\Http\Controllers;

use App\Enums\EstadoTarea;
use App\Enums\PrioridadTarea;
use App\Http\Requests\CambiarEstadoTareaRequest;
use App\Http\Requests\FiltroTareasRequest;
use App\Http\Requests\TareaRequest;
use App\Models\ColumnaTablero;
use App\Models\Contexto;
use App\Models\Recordatorio;
use App\Models\Tarea;
use App\Services\Hoy\ListasHoy;
use App\Services\Tareas\ItemLista;
use App\Services\Tareas\ListaTareas;
use App\Support\Aviso;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

class TareaController extends Controller
{
    /** Lista unificada de tareas y recordatorios, agrupada por tiempo (los enlaces viejos ?vista=tablero van al Tablero). */
    public function index(FiltroTareasRequest $request, ListaTareas $lista): View|RedirectResponse
    {
        $filtros = $request->validated();

        if (($filtros['vista'] ?? 'lista') === 'tablero') {
            return redirect()->route('tablero.index', array_filter(['contexto' => $filtros['contexto'] ?? null]));
        }

        $columnasOrden = ColumnaTablero::ordenadas()->get();

        return view('tareas.index', $lista->armar($filtros) + [
            'resumen' => $lista->resumen(),
            'contextosFiltro' => Contexto::opcionesPorTipo(soloConTareas: true),
            'contextos' => Contexto::opcionesPorTipo(),
            'contextoFiltrado' => isset($filtros['contexto']) ? Contexto::find($filtros['contexto']) : null,
            'columnasOrden' => $columnasOrden,
            'tareasAbiertas' => Tarea::abiertas()->orWhereIn('id', Recordatorio::whereNotNull('tarea_id')->select('tarea_id'))->orderBy('titulo')->get(['id', 'titulo']),
            'filtros' => [
                'tipo' => $filtros['tipo'] ?? 'todo',
                'estado' => $filtros['estado'] ?? 'abiertas',
                'prioridad' => $filtros['prioridad'] ?? null,
                'contexto' => $filtros['contexto'] ?? null,
                'q' => $filtros['q'] ?? null,
            ],
            'hoy' => today(),
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

        // Modal de tareas: solo confirma; la página se recarga y muestra el aviso.
        if ($request->expectsJson() && $request->hasHeader('X-Modal')) {
            Aviso::guardar('Tarea creada.');

            return response()->json(['id' => $tarea->id, 'mensaje' => 'Tarea creada.'], 201);
        }

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

        return redirect()->route('tareas.index')->with(Aviso::flash('Tarea creada.'));
    }

    public function show(Tarea $tarea): RedirectResponse
    {
        return redirect()->route('tareas.edit', $tarea);
    }

    public function edit(Tarea $tarea): View
    {
        return view('tareas.editar', $this->datosFormulario($tarea));
    }

    public function update(TareaRequest $request, Tarea $tarea): RedirectResponse|JsonResponse
    {
        $tarea->update($request->validated());

        if ($request->expectsJson()) {
            Aviso::guardar('Tarea actualizada.');

            return response()->json(['id' => $tarea->id, 'mensaje' => 'Tarea actualizada.']);
        }

        return redirect()->route('tareas.index')->with(Aviso::flash('Tarea actualizada.'));
    }

    public function destroy(Request $request, Tarea $tarea): Response|RedirectResponse
    {
        $tarea->delete();

        if ($request->header('HX-Request')) {
            return response('');
        }

        return redirect()->route('tareas.index')->with(Aviso::flash('Tarea eliminada.'));
    }

    /** Cambia el estado con un clic. Con HTMX devuelve solo la fila actualizada. */
    public function cambiarEstado(CambiarEstadoTareaRequest $request, Tarea $tarea, ListasHoy $listas): View|RedirectResponse|JsonResponse
    {
        $tarea->update($request->validated());

        if ($request->expectsJson()) {
            return response()->json(['id' => $tarea->id, 'estado' => $tarea->estado->value] + $this->listaHoy($listas));
        }

        if ($request->header('HX-Request')) {
            return view('tareas._item', ['item' => ItemLista::deTarea($tarea), 'hoy' => today()]);
        }

        return back()->with(Aviso::flash('Estado actualizado.'));
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

    private function datosFormulario(Tarea $tarea): array
    {
        return [
            'tarea' => $tarea,
            'prioridades' => PrioridadTarea::cases(),
            'estados' => EstadoTarea::cases(),
            'contextos' => Contexto::opcionesPorTipo(),
        ];
    }
}
