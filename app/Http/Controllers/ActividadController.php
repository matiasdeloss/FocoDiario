<?php

namespace App\Http\Controllers;

use App\Enums\TipoContexto;
use App\Http\Requests\ActividadRequest;
use App\Models\Contexto;
use Illuminate\Http\RedirectResponse;

/**
 * Actividades de la agenda (materias con color). Por dentro son contextos de tipo materia con el campo color:
 * así también aparecen como destino de las notas, sin duplicar estructuras.
 */
class ActividadController extends Controller
{
    public function store(ActividadRequest $request): RedirectResponse
    {
        Contexto::create([
            'nombre' => $request->validated('nombre'),
            'color' => $request->validated('color'),
            'tipo' => TipoContexto::Materia,
            'contexto_padre_id' => null,
        ]);

        return back()->with('estado', 'Actividad creada.');
    }

    public function update(ActividadRequest $request, Contexto $actividad): RedirectResponse
    {
        $this->asegurarQueEsActividad($actividad);

        $actividad->update($request->validated());

        return back()->with('estado', 'Actividad actualizada.');
    }

    public function destroy(Contexto $actividad): RedirectResponse
    {
        $this->asegurarQueEsActividad($actividad);

        $actividad->delete();

        return back()->with('estado', 'Actividad eliminada. Sus cajas quedaron sin actividad.');
    }

    /** Solo se gestionan desde la agenda los contextos que tienen color. */
    private function asegurarQueEsActividad(Contexto $actividad): void
    {
        abort_if($actividad->color === null, 404);
    }
}
