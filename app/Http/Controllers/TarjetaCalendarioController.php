<?php

namespace App\Http\Controllers;

use App\Http\Requests\ActualizarTarjetaRequest;
use App\Http\Requests\CrearTarjetaRequest;
use App\Http\Requests\PaginaTarjetasRequest;
use App\Models\Nota;
use App\Models\Recordatorio;
use App\Models\SesionEstudio;
use App\Models\Tarea;
use App\Services\Calendario\DetalleCalendario;
use App\Services\Calendario\EventosCalendario;
use App\Services\Calendario\TarjetasCalendario;
use App\Services\RegistroEstudio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/** Tarjetas simples del calendario: crear, editar título y comentario, eliminar y paginar el panel. */
class TarjetaCalendarioController extends Controller
{
    public function __construct(
        private readonly TarjetasCalendario $tarjetas,
        private readonly EventosCalendario $calendario,
        private readonly DetalleCalendario $detalle,
    ) {
    }

    public function store(CrearTarjetaRequest $request): JsonResponse
    {
        $tarjeta = $this->tarjetas->crear($request->validated('tipo'), $request->validated('fecha'));
        $datos = $this->tarjetas->datos($tarjeta);

        return response()->json([
            'tarjeta' => $datos,
            'panel' => $datos['fecha'] === null ? $this->tarjetas->html($tarjeta, nueva: true) : null,
            'evento' => $datos['fecha'] !== null ? $this->evento($tarjeta) : null,
            // Una tarjeta nueva sin fecha queda en "Por ubicar": el panel lo confirma con un aviso de éxito.
            'mensaje' => $datos['fecha'] === null ? 'Tarjeta agregada a Por ubicar.' : null,
        ], 201);
    }

    /** Siguiente página de tarjetas sin ubicar de un tipo ("ver más"). */
    public function index(PaginaTarjetasRequest $request, string $tipo): JsonResponse
    {
        $filas = $this->tarjetas->pagina($tipo, array_map('intval', $request->validated('excluir') ?? []));

        $contextos = $this->tarjetas->contextosDelPanel();

        return response()->json([
            'html' => $filas->take(TarjetasCalendario::POR_PAGINA)->map(fn ($fila) => $this->tarjetas->html($fila, contextos: $contextos))->implode(''),
            'hayMas' => $filas->count() > TarjetasCalendario::POR_PAGINA,
        ]);
    }

    public function update(ActualizarTarjetaRequest $request, string $tipo, int $id): JsonResponse
    {
        $tarjeta = $this->tarjetas->buscar($tipo, $id);
        $this->tarjetas->actualizar($tarjeta, $request->validated());

        $tarjeta = $tarjeta->fresh();

        return response()->json([
            'tarjeta' => $this->tarjetas->datos($tarjeta),
            'detalle' => $this->detalle->datos($tarjeta),
        ]);
    }

    public function destroy(string $tipo, int $id, RegistroEstudio $registro): Response
    {
        if ($tipo === 'sesion') {
            $sesion = SesionEstudio::findOrFail($id);
            $registro->borrarSesion($sesion);

            return response()->noContent();
        }

        $this->tarjetas->buscar($tipo, $id)->delete();

        return response()->noContent();
    }

    private function evento(Model $tarjeta): array
    {
        return match (true) {
            $tarjeta instanceof Tarea => $this->calendario->eventoTarea($tarjeta),
            $tarjeta instanceof Recordatorio => $this->calendario->eventoRecordatorio($tarjeta),
            $tarjeta instanceof Nota => $this->calendario->eventoNota($tarjeta),
        };
    }
}
