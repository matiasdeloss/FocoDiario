<?php

namespace App\Http\Controllers;

use App\Enums\EstadoTarea;
use App\Enums\PrioridadTarea;
use App\Http\Requests\ColumnaTableroRequest;
use App\Http\Requests\EliminarColumnaTableroRequest;
use App\Http\Requests\MoverColumnaTableroRequest;
use App\Http\Requests\MoverTareaColumnaRequest;
use App\Http\Requests\TarjetaColumnaRequest;
use App\Models\ColumnaTablero;
use App\Models\Tarea;
use App\Support\Aviso;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Columnas personalizables del tablero de tareas y movimiento de tareas entre ellas. */
class ColumnaTableroController extends Controller
{
    public function store(ColumnaTableroRequest $request): RedirectResponse|JsonResponse
    {
        $columna = ColumnaTablero::create([
            'nombre' => $request->validated('nombre'),
            'categoria' => $request->validated('categoria') ?? EstadoTarea::EnProgreso,
            'posicion' => (int) ColumnaTablero::max('posicion') + 1,
        ]);

        return $this->respuesta($request, 'Columna creada.', ['columna' => $columna], 201);
    }

    public function update(ColumnaTableroRequest $request, ColumnaTablero $columna): RedirectResponse|JsonResponse
    {
        $columna->update($request->validated());

        // Si cambió el tipo, las tareas de la columna adoptan el estado nuevo (una sola consulta).
        if ($columna->wasChanged('categoria')) {
            $columna->tareas()->update(['estado' => $columna->categoria]);
        }

        return $this->respuesta($request, 'Columna actualizada.', ['columna' => $columna]);
    }

    /** Elimina la columna y pasa sus tareas a la columna elegida (su estado se ajusta al de destino). */
    public function destroy(EliminarColumnaTableroRequest $request, ColumnaTablero $columna): RedirectResponse|JsonResponse
    {
        DB::transaction(function () use ($request, $columna) {
            $destino = $request->validated('reasignar_a');

            if ($destino !== null) {
                $categoria = ColumnaTablero::findOrFail($destino)->categoria;
                $columna->tareas()->update(['columna_id' => $destino, 'estado' => $categoria]);
            }

            $columna->delete();
        });

        return $this->respuesta($request, 'Columna eliminada.');
    }

    public function mover(MoverColumnaTableroRequest $request, ColumnaTablero $columna): RedirectResponse|JsonResponse
    {
        $orden = ColumnaTablero::ordenadas()->pluck('id')->all();
        $indice = array_search($columna->id, $orden, true);
        $destino = $indice + ($request->validated('direccion') === 'izquierda' ? -1 : 1);

        if (isset($orden[$destino])) {
            [$orden[$indice], $orden[$destino]] = [$orden[$destino], $orden[$indice]];

            DB::transaction(function () use ($orden) {
                foreach ($orden as $posicion => $id) {
                    ColumnaTablero::whereKey($id)->update(['posicion' => $posicion]);
                }
            });
        }

        return $this->respuesta($request, 'Columna movida.', ['orden' => $orden]);
    }

    /** Alta rápida de una tarjeta al pie de una columna: solo título, prioridad media. */
    public function tarjeta(TarjetaColumnaRequest $request, ColumnaTablero $columna): RedirectResponse|JsonResponse
    {
        $tarea = Tarea::create([
            'titulo' => $request->validated('titulo'),
            'prioridad' => PrioridadTarea::Media,
            'columna_id' => $columna->id,
            'orden' => (int) Tarea::where('columna_id', $columna->id)->max('orden') + 1,
        ]);

        return $this->respuesta($request, 'Tarjeta añadida.', [
            'id' => $tarea->id,
            'html' => view('tablero._tarjeta', ['tarea' => $tarea->load('columna'), 'columnasOrden' => ColumnaTablero::ordenadas()->get()])->render(),
        ], 201);
    }

    /**
     * Mueve una tarea a otra columna (su estado pasa a ser el tipo de esa columna) y, si llega el orden,
     * deja las tarjetas de la columna en ese orden. Sin orden, la tarjeta queda arriba de la columna de destino.
     */
    public function moverTarea(MoverTareaColumnaRequest $request, Tarea $tarea): RedirectResponse|JsonResponse
    {
        $destino = (int) $request->validated('columna_id');
        $orden = $request->validated('orden');
        $cambia = $tarea->columna_id !== $destino;

        DB::transaction(function () use ($tarea, $destino, $orden, $cambia) {
            if ($cambia) {
                $tarea->update([
                    'columna_id' => $destino,
                    'orden' => $orden === null ? (int) Tarea::where('columna_id', $destino)->min('orden') - 1 : $tarea->orden,
                ]);
            }

            foreach (array_values($orden ?? []) as $posicion => $id) {
                Tarea::whereKey($id)->where('columna_id', $destino)->update(['orden' => $posicion + 1]);
            }
        });

        return $this->respuesta($request, 'Tarea movida.', [
            'id' => $tarea->id,
            'columna_id' => $tarea->columna_id,
            'estado' => $tarea->estado->value,
        ]);
    }

    private function respuesta(Request $request, string $mensaje, array $datos = [], int $codigo = 200): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            // Los modales recargan la página tras guardar: el aviso viaja en la sesión.
            if ($request->hasHeader('X-Modal')) {
                Aviso::guardar($mensaje);
            }

            return response()->json($datos + ['mensaje' => $mensaje], $codigo);
        }

        return redirect()->route('tablero.index')->with(Aviso::flash($mensaje));
    }
}
