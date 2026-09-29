<?php

namespace App\Http\Controllers;

use App\Enums\EstadoTarea;
use App\Http\Requests\EventosCalendarioRequest;
use App\Http\Requests\FechaNotaRequest;
use App\Http\Requests\FechaRecordatorioRequest;
use App\Http\Requests\FechaTareaRequest;
use App\Models\Nota;
use App\Models\Recordatorio;
use App\Models\Tarea;
use App\Services\Calendario\EventosCalendario;
use App\Services\Calendario\TarjetasCalendario;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CalendarioController extends Controller
{
    public function index(Request $request, TarjetasCalendario $tarjetas): View
    {
        $datos = $request->validate(['fecha' => ['nullable', 'date_format:Y-m-d']]);

        return view('calendario.index', [
            'fechaInicial' => $datos['fecha'] ?? today()->toDateString(),
            ...$tarjetas->panel(),
        ]);
    }

    public function eventos(EventosCalendarioRequest $request, EventosCalendario $calendario): JsonResponse
    {
        $desde = Carbon::parse($request->validated('start'))->startOfDay();
        $hasta = Carbon::parse($request->validated('end'))->startOfDay();

        return response()->json($calendario->eventos($desde, $hasta, $request->tipos()));
    }

    /** Asigna o quita (fecha null) la fecha límite de una tarea. */
    public function fechaTarea(FechaTareaRequest $request, Tarea $tarea, EventosCalendario $calendario, TarjetasCalendario $tarjetas): JsonResponse
    {
        if ($tarea->estado === EstadoTarea::Completada) {
            return response()->json(['message' => 'Las tareas completadas no se pueden reasignar.'], 422);
        }

        $tarea->update(['fecha_limite' => $request->validated('fecha')]);

        return response()->json([
            'evento' => $tarea->fecha_limite ? $calendario->eventoTarea($tarea) : null,
            'panel' => $tarea->fecha_limite ? null : $tarjetas->html($tarea),
        ]);
    }

    /** Asigna o quita (fecha null) la fecha de una nota. */
    public function fechaNota(FechaNotaRequest $request, Nota $nota, EventosCalendario $calendario, TarjetasCalendario $tarjetas): JsonResponse
    {
        $nota->update(['fecha' => $request->validated('fecha')]);

        return response()->json([
            'evento' => $nota->fecha ? $calendario->eventoNota($nota) : null,
            'panel' => $nota->fecha ? null : $tarjetas->html($nota),
        ]);
    }

    /** Cambia el día y la hora de un recordatorio, o lo devuelve al panel (recordar_en null). */
    public function fechaRecordatorio(FechaRecordatorioRequest $request, Recordatorio $recordatorio, EventosCalendario $calendario, TarjetasCalendario $tarjetas): JsonResponse
    {
        if ($recordatorio->avisado_en !== null) {
            return response()->json(['message' => 'Los recordatorios ya avisados no se pueden reubicar.'], 422);
        }

        $momento = $request->momento();
        $recordatorio->update(['recordar_en' => $momento ? $tarjetas->fechaHora($momento) : null]);

        return response()->json([
            'recordar_en' => $recordatorio->recordar_en?->format('Y-m-d\TH:i:s'),
            'evento' => $recordatorio->recordar_en ? $calendario->eventoRecordatorio($recordatorio) : null,
            'panel' => $recordatorio->recordar_en ? null : $tarjetas->html($recordatorio),
        ]);
    }
}
