<?php

namespace App\Http\Controllers;

use App\Http\Requests\RecordatorioRequest;
use App\Models\Recordatorio;
use App\Models\Tarea;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class RecordatorioController extends Controller
{
    public function index(): View
    {
        $recordatorios = Recordatorio::with('tarea')
            ->orderByRaw('avisado_en is not null')
            ->orderBy('recordar_en')
            ->get();

        return view('recordatorios.index', ['recordatorios' => $recordatorios]);
    }

    public function create(): View
    {
        return view('recordatorios.crear', $this->datosFormulario(new Recordatorio));
    }

    public function store(RecordatorioRequest $request): RedirectResponse
    {
        Recordatorio::create($request->validated());

        return redirect()->route('recordatorios.index')->with('estado', 'Recordatorio creado.');
    }

    public function show(Recordatorio $recordatorio): RedirectResponse
    {
        return redirect()->route('recordatorios.edit', $recordatorio);
    }

    public function edit(Recordatorio $recordatorio): View
    {
        return view('recordatorios.editar', $this->datosFormulario($recordatorio));
    }

    public function update(RecordatorioRequest $request, Recordatorio $recordatorio): RedirectResponse
    {
        $recordatorio->update($request->validated());

        return redirect()->route('recordatorios.index')->with('estado', 'Recordatorio actualizado.');
    }

    public function destroy(Request $request, Recordatorio $recordatorio): Response|RedirectResponse
    {
        $recordatorio->delete();

        if ($request->header('HX-Request')) {
            return response('');
        }

        return redirect()->route('recordatorios.index')->with('estado', 'Recordatorio eliminado.');
    }

    /** Marca el recordatorio como avisado. Con HTMX devuelve solo la fila actualizada. */
    public function avisar(Request $request, Recordatorio $recordatorio): View|RedirectResponse
    {
        $recordatorio->update(['avisado_en' => now()]);

        if ($request->header('HX-Request')) {
            return view('recordatorios._fila', ['recordatorio' => $recordatorio->load('tarea')]);
        }

        return back()->with('estado', 'Recordatorio marcado como avisado.');
    }

    private function datosFormulario(Recordatorio $recordatorio): array
    {
        // Tareas abiertas, más la ya vinculada aunque esté completada.
        $tareas = Tarea::abiertas()
            ->when($recordatorio->tarea_id, fn ($consulta, $id) => $consulta->orWhere('id', $id))
            ->orderBy('titulo')
            ->get(['id', 'titulo']);

        return ['recordatorio' => $recordatorio, 'tareas' => $tareas];
    }
}
