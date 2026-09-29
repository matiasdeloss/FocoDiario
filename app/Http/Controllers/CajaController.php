<?php

namespace App\Http\Controllers;

use App\Enums\ZonaSemana;
use App\Http\Requests\CajaRequest;
use App\Http\Requests\CajaSemanaRequest;
use App\Http\Requests\LayoutCajasRequest;
use App\Http\Resources\CajaResource;
use App\Models\Caja;
use App\Models\Contexto;
use App\Services\Agenda\Semana;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/** Cajas de la agenda. Responde JSON: la hoja del día las guarda sola mientras se escribe. */
class CajaController extends Controller
{
    /** Ancho y alto con los que nace una caja nueva (12 columnas, filas de 24 px). */
    private const ANCHO_INICIAL = 6;

    private const ALTO_INICIAL = 8;

    public function store(CajaRequest $request): JsonResponse
    {
        $datos = $request->datosCaja();
        $fecha = $datos['fecha'];

        // Si no se indica lugar, la caja nueva va debajo de las que ya hay.
        $ultimaFila = (int) Caja::query()->delDia($fecha)->max(DB::raw('y + alto'));

        $caja = Caja::create([
            'ancho' => self::ANCHO_INICIAL,
            'alto' => self::ALTO_INICIAL,
            'x' => 0,
            'y' => min($ultimaFila, Caja::MAX_FILA),
            'orden' => Caja::query()->delDia($fecha)->count(),
            ...$datos,
        ]);

        $caja->load('actividad:id,nombre,color');

        return response()->json([
            'caja' => new CajaResource($caja),
            'html' => view('agenda._caja', [
                'caja' => $caja,
                'actividades' => Contexto::query()->actividades()->get(),
            ])->render(),
        ], 201);
    }

    public function update(CajaRequest $request, Caja $caja): JsonResponse
    {
        $caja->update($request->datosCaja());

        return response()->json(['caja' => new CajaResource($caja->fresh())]);
    }

    public function destroy(Caja $caja): JsonResponse
    {
        $caja->delete();

        return response()->json(['ok' => true]);
    }

    /** Guarda de una vez la posición y el tamaño de las cajas de una hoja. */
    public function layout(LayoutCajasRequest $request, string $fecha): JsonResponse
    {
        abort_unless(Semana::esFechaValida($fecha), 404);

        DB::transaction(function () use ($request) {
            foreach ($request->validated('cajas') as $datos) {
                Caja::query()->whereKey($datos['id'])->update([
                    'x' => $datos['x'],
                    'y' => $datos['y'],
                    'ancho' => $datos['ancho'],
                    'alto' => $datos['alto'],
                    'updated_at' => now(),
                ]);
            }
        });

        return response()->json(['ok' => true]);
    }

    /** Guarda las cajas Notas o Pendiente de una semana (se crean la primera vez que se escribe en ellas). */
    public function guardarSemana(CajaSemanaRequest $request, string $semana, ZonaSemana $zona): JsonResponse
    {
        abort_unless(Semana::esFechaValida($semana), 404);

        $lunes = Semana::lunesDe($semana)->toDateString();

        $caja = Caja::query()->deLaSemana($lunes)->where('zona', $zona)->first() ?? new Caja([
            'semana' => $lunes,
            'zona' => $zona,
            'titulo' => $zona->etiqueta(),
        ]);

        $caja->fill($request->datosCaja())->save();

        return response()->json(['caja' => new CajaResource($caja)]);
    }
}
