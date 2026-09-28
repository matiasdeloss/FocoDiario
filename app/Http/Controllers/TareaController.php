<?php

namespace App\Http\Controllers;

use App\Enums\EstadoTarea;
use App\Enums\PrioridadTarea;
use App\Http\Requests\CambiarEstadoTareaRequest;
use App\Http\Requests\FiltroTareasRequest;
use App\Http\Requests\TareaRequest;
use App\Models\Tarea;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TareaController extends Controller
{
    public function index(FiltroTareasRequest $request): View
    {
        $filtros = $request->validated();

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
        ]);
    }

    public function create(): View
    {
        return view('tareas.crear', $this->datosFormulario(new Tarea([
            'prioridad' => PrioridadTarea::Media,
            'estado' => EstadoTarea::Pendiente,
        ])));
    }

    public function store(TareaRequest $request): RedirectResponse
    {
        Tarea::create($request->validated());

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
    public function cambiarEstado(CambiarEstadoTareaRequest $request, Tarea $tarea): View|RedirectResponse
    {
        $tarea->update($request->validated());

        if ($request->header('HX-Request')) {
            return view('tareas._fila', ['tarea' => $tarea]);
        }

        return back()->with('estado', 'Estado actualizado.');
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
