<?php

namespace App\Http\Controllers;

use App\Enums\EstadoSesion;
use App\Http\Requests\IntervaloEstudioRequest;
use App\Http\Requests\SesionEstudioRequest;
use App\Models\SesionEstudio;
use App\Services\RegistroEstudio;
use App\Support\Aviso;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SesionEstudioController extends Controller
{
    /** Empieza una sesión. Si quedó otra en curso (pestaña cerrada), se da por terminada. */
    public function store(SesionEstudioRequest $request): JsonResponse
    {
        SesionEstudio::enCurso()->update([
            'estado' => EstadoSesion::Finalizada,
            'finalizada_en' => now(),
        ]);

        $sesion = SesionEstudio::create([
            ...$request->validated(),
            'estado' => EstadoSesion::EnCurso,
            'iniciada_en' => now(),
        ]);

        return response()->json(['id' => $sesion->id], 201);
    }

    public function finalizar(SesionEstudio $sesion): JsonResponse
    {
        if ($sesion->estado === EstadoSesion::EnCurso) {
            $sesion->update(['estado' => EstadoSesion::Finalizada, 'finalizada_en' => now()]);
        }

        return response()->json(['id' => $sesion->id, 'estado' => $sesion->estado->value]);
    }

    public function registrarIntervalo(IntervaloEstudioRequest $request, SesionEstudio $sesion, RegistroEstudio $registro): JsonResponse
    {
        $intervalo = $registro->registrarIntervalo($sesion, $request->validated());

        return response()->json([
            'id' => $intervalo->id,
            'bloque_tiempo_id' => $intervalo->bloque_tiempo_id,
        ], $intervalo->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(Request $request, SesionEstudio $sesion, RegistroEstudio $registro): Response|RedirectResponse
    {
        $registro->borrarSesion($sesion);

        // HX-Refresh recarga la página: el aviso viaja en la sesión.
        if ($request->header('HX-Request')) {
            Aviso::guardar('Sesión eliminada.');

            return response('', 200, ['HX-Refresh' => 'true']);
        }

        return redirect()->route('estudio.historial')->with(Aviso::flash('Sesión eliminada.'));
    }
}
