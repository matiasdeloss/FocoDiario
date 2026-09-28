<?php

namespace App\Http\Controllers;

use App\Enums\EstadoTarea;
use App\Http\Requests\EventosCalendarioRequest;
use App\Http\Requests\FechaRecordatorioRequest;
use App\Http\Requests\FechaTareaRequest;
use App\Models\Recordatorio;
use App\Models\Tarea;
use App\Services\Calendario\EventosCalendario;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CalendarioController extends Controller
{
    public function index(Request $request): View
    {
        $datos = $request->validate(['fecha' => ['nullable', 'date_format:Y-m-d']]);

        return view('calendario.index', [
            'fechaInicial' => $datos['fecha'] ?? today()->toDateString(),
            'sinFecha' => Tarea::abiertas()
                ->whereNull('fecha_limite')
                ->orderByRaw("case prioridad when 'alta' then 0 when 'media' then 1 else 2 end")
                ->orderBy('id')
                ->limit(100)
                ->get(),
        ]);
    }

    public function eventos(EventosCalendarioRequest $request, EventosCalendario $calendario): JsonResponse
    {
        $desde = Carbon::parse($request->validated('start'))->startOfDay();
        $hasta = Carbon::parse($request->validated('end'))->startOfDay();

        return response()->json($calendario->eventos($desde, $hasta, $request->tipos()));
    }

    /** Asigna o quita (fecha null) la fecha límite de una tarea. */
    public function fechaTarea(FechaTareaRequest $request, Tarea $tarea, EventosCalendario $calendario): JsonResponse
    {
        if ($tarea->estado === EstadoTarea::Completada) {
            return response()->json(['message' => 'Las tareas completadas no se pueden reasignar.'], 422);
        }

        $tarea->update(['fecha_limite' => $request->validated('fecha')]);

        return response()->json([
            'evento' => $tarea->fecha_limite ? $calendario->eventoTarea($tarea) : null,
            'panel' => $tarea->fecha_limite ? null : view('calendario._tarea-panel', ['tarea' => $tarea])->render(),
        ]);
    }

    /** Cambia el día (y la hora) de un recordatorio. */
    public function fechaRecordatorio(FechaRecordatorioRequest $request, Recordatorio $recordatorio): JsonResponse
    {
        $recordatorio->update([
            'recordar_en' => Carbon::createFromFormat('Y-m-d\TH:i:s', $request->validated('recordar_en')),
        ]);

        return response()->json(['recordar_en' => $recordatorio->recordar_en->format('Y-m-d\TH:i:s')]);
    }
}
