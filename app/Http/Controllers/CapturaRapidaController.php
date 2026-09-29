<?php

namespace App\Http\Controllers;

use App\Http\Requests\CapturaRapidaRequest;
use App\Models\Contexto;
use App\Models\Nota;
use App\Models\Recordatorio;
use App\Models\Tarea;
use App\Services\Hoy\CapturaRapida;
use App\Services\Hoy\ListasHoy;
use App\Services\Hoy\SemanaHoy;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class CapturaRapidaController extends Controller
{
    /**
     * Crea el registro del tipo elegido. Con HTMX responde el formulario limpio más los fragmentos
     * de Hoy que cambian (tareas, recordatorios, semana) para reemplazarlos sin recargar.
     */
    public function __invoke(CapturaRapidaRequest $request, CapturaRapida $captura, ListasHoy $listas, SemanaHoy $semana): View|RedirectResponse
    {
        $creado = $captura->crear($request->datosCaptura());
        $mensaje = $captura->mensaje($creado);

        if (! $request->header('HX-Request')) {
            return redirect()->route('hoy')->with('estado', $mensaje);
        }

        $fecha = match (true) {
            $creado instanceof Tarea => $creado->fecha_limite,
            $creado instanceof Recordatorio => $creado->recordar_en,
            $creado instanceof Nota => $creado->fecha,
        };

        return view('hoy._captura-respuesta', [
            'valores' => ['tipo' => $request->input('tipo')],
            'destinos' => Contexto::opciones(),
            'mensaje' => $mensaje,
            'tareas' => $creado instanceof Tarea ? $listas->tareas() : null,
            'recordatorios' => $creado instanceof Recordatorio ? $listas->recordatorios() : null,
            'semana' => $fecha ? $semana->datos() : null,
        ]);
    }
}
