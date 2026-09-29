<?php

namespace App\Http\Controllers;

use App\Http\Requests\RecordatorioRequest;
use App\Models\Recordatorio;
use App\Models\Tarea;
use App\Services\Tareas\ItemLista;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

class RecordatorioController extends Controller
{
    /** Recordatorios y Tareas comparten una sola pantalla: esta ruta lleva a la lista unificada, filtrada por recordatorios. */
    public function index(): RedirectResponse
    {
        return redirect()->route('tareas.index', ['tipo' => 'recordatorio']);
    }

    public function create(Request $request): View
    {
        // ?fecha=AAAA-MM-DD precarga el día a las 09:00 (viene del calendario).
        $fecha = Carbon::hasFormat((string) $request->query('fecha'), 'Y-m-d') ? $request->query('fecha') : null;

        return view('recordatorios.crear', $this->datosFormulario(new Recordatorio([
            'recordar_en' => $fecha ? Carbon::parse($fecha.' 09:00') : null,
        ])));
    }

    public function store(RecordatorioRequest $request): RedirectResponse|JsonResponse
    {
        $recordatorio = Recordatorio::create($request->validated());

        // Modal de recordatorios: solo confirma; la página se recarga y muestra el aviso.
        if ($request->expectsJson()) {
            $request->session()->flash('estado', 'Recordatorio creado.');

            return response()->json(['id' => $recordatorio->id, 'mensaje' => 'Recordatorio creado.'], 201);
        }

        return redirect()->route('tareas.index')->with('estado', 'Recordatorio creado.');
    }

    public function show(Recordatorio $recordatorio): RedirectResponse
    {
        return redirect()->route('recordatorios.edit', $recordatorio);
    }

    public function edit(Recordatorio $recordatorio): View
    {
        return view('recordatorios.editar', $this->datosFormulario($recordatorio));
    }

    public function update(RecordatorioRequest $request, Recordatorio $recordatorio): RedirectResponse|JsonResponse
    {
        $recordatorio->update($request->validated());

        if ($request->expectsJson()) {
            $request->session()->flash('estado', 'Recordatorio actualizado.');

            return response()->json(['id' => $recordatorio->id, 'mensaje' => 'Recordatorio actualizado.']);
        }

        return redirect()->route('tareas.index')->with('estado', 'Recordatorio actualizado.');
    }

    public function destroy(Request $request, Recordatorio $recordatorio): Response|RedirectResponse
    {
        $recordatorio->delete();

        if ($request->header('HX-Request')) {
            return response('');
        }

        return redirect()->route('tareas.index')->with('estado', 'Recordatorio eliminado.');
    }

    /** Marca el recordatorio como avisado. Con HTMX devuelve solo la fila actualizada. */
    public function avisar(Request $request, Recordatorio $recordatorio): View|RedirectResponse|JsonResponse
    {
        $recordatorio->update(['avisado_en' => now()]);

        if ($request->expectsJson()) {
            return response()->json(['id' => $recordatorio->id, 'avisado' => true]);
        }

        if ($request->header('HX-Request')) {
            return view('tareas._item', ['item' => ItemLista::deRecordatorio($recordatorio->load('tarea')), 'hoy' => today()]);
        }

        return back()->with('estado', 'Recordatorio marcado como avisado.');
    }

    /** Deshace el aviso: el recordatorio vuelve a pendiente (conserva su fecha, así que recupera su lugar por fecha). */
    public function reactivar(Request $request, Recordatorio $recordatorio): View|RedirectResponse|JsonResponse
    {
        $recordatorio->update(['avisado_en' => null]);

        if ($request->expectsJson()) {
            return response()->json(['id' => $recordatorio->id, 'avisado' => false]);
        }

        if ($request->header('HX-Request')) {
            return view('tareas._item', ['item' => ItemLista::deRecordatorio($recordatorio->load('tarea')), 'hoy' => today()]);
        }

        return back()->with('estado', 'Recordatorio vuelto a pendiente.');
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
