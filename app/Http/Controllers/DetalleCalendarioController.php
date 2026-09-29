<?php

namespace App\Http\Controllers;

use App\Services\Calendario\DetalleCalendario;
use Illuminate\Http\JsonResponse;

/** Detalle completo de una card del calendario, para el panel lateral. */
class DetalleCalendarioController extends Controller
{
    public function show(DetalleCalendario $detalle, string $tipo, int $id): JsonResponse
    {
        return response()->json(['detalle' => $detalle->datos($detalle->buscar($tipo, $id))]);
    }
}
