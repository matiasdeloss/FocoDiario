<?php

namespace App\Http\Controllers;

use App\Http\Requests\PosponerRecordatorioRequest;
use App\Http\Requests\RecordatorioRequest;
use App\Models\Recordatorio;
use App\Models\Tarea;
use App\Services\Tareas\ItemLista;
use App\Support\Aviso;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

class RecordatorioController extends Controller
{
    /** Tope de toasts de recordatorio que se piden de una vez. */
    private const VENCIDOS_MAXIMO = 5;

    /** Recordatorios y Tareas comparten una sola pantalla: esta ruta lleva a la lista unificada, filtrada por recordatorios. */
    public function index(): RedirectResponse
    {
        return redirect()->route('tareas.index', ['tipo' => 'recordatorio']);
    }

    /**
     * Recordatorios cuya hora ya llegó y todavía no se marcaron como avisados. Los consulta cada minuto
     * resources/js/recordatorios-avisos.js para mostrarlos como toast. Solo los de las últimas 24 h:
     * los más viejos ya están en Hoy y en Tareas como vencidos, y no deben aparecer todos de golpe.
     */
    public function vencidos(): JsonResponse
    {
        $ahora = now();

        $recordatorios = Recordatorio::query()
            ->pendientes()
            ->whereBetween('recordar_en', [$ahora->copy()->subDay(), $ahora])
            ->orderBy('recordar_en')
            ->limit(self::VENCIDOS_MAXIMO)
            ->get();

        return response()->json([
            'recordatorios' => $recordatorios->map(fn (Recordatorio $recordatorio) => [
                'id' => $recordatorio->id,
                'mensaje' => $recordatorio->mensaje !== '' ? $recordatorio->mensaje : 'Recordatorio sin título',
                'descripcion' => $recordatorio->descripcion,
                'hora' => $recordatorio->recordar_en->format('H:i'),
                'recordar_en' => $recordatorio->recordar_en->format('Y-m-d\TH:i'),
                'url_avisar' => route('recordatorios.avisar', $recordatorio),
                'url_posponer' => route('recordatorios.posponer', $recordatorio),
            ])->values(),
        ]);
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
            Aviso::guardar('Recordatorio creado.');

            return response()->json(['id' => $recordatorio->id, 'mensaje' => 'Recordatorio creado.'], 201);
        }

        return redirect()->route('tareas.index')->with(Aviso::flash('Recordatorio creado.'));
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
            Aviso::guardar('Recordatorio actualizado.');

            return response()->json(['id' => $recordatorio->id, 'mensaje' => 'Recordatorio actualizado.']);
        }

        return redirect()->route('tareas.index')->with(Aviso::flash('Recordatorio actualizado.'));
    }

    public function destroy(Request $request, Recordatorio $recordatorio): Response|RedirectResponse
    {
        $recordatorio->delete();

        if ($request->header('HX-Request')) {
            return response('');
        }

        return redirect()->route('tareas.index')->with(Aviso::flash('Recordatorio eliminado.'));
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

        return back()->with(Aviso::flash('Recordatorio marcado como avisado.'));
    }

    /** "Más tarde" desde el toast: el recordatorio vuelve a sonar dentro de unos minutos (se cuentan desde ahora, con la hora del servidor). */
    public function posponer(PosponerRecordatorioRequest $request, Recordatorio $recordatorio): JsonResponse
    {
        if ($recordatorio->avisado_en !== null) {
            return response()->json(['message' => 'Este recordatorio ya estaba marcado como avisado.'], 422);
        }

        $recordatorio->update(['recordar_en' => now()->addMinutes($request->minutos())->startOfMinute()]);

        return response()->json([
            'id' => $recordatorio->id,
            'recordar_en' => $recordatorio->recordar_en->format('Y-m-d\TH:i'),
        ]);
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

        return back()->with(Aviso::flash('Recordatorio vuelto a pendiente.'));
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
