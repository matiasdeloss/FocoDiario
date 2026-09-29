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

        // Si cambió el tipo, las tareas de la columna adoptan el estado nuevo.
        $columna->tareas()->get()->each(fn (Tarea $tarea) => $tarea->update(['columna_id' => $columna->id, 'estado' => $columna->categoria]));

        return $this->respuesta($request, 'Columna actualizada.', ['columna' => $columna]);
    }

    /** Elimina la columna y pasa sus tareas a la columna elegida (su estado se ajusta al de destino). */
    public function destroy(EliminarColumnaTableroRequest $request, ColumnaTablero $columna): RedirectResponse|JsonResponse
    {
        DB::transaction(function () use ($request, $columna) {
            $destino = $request->validated('reasignar_a');

            if ($destino !== null) {
                $categoria = ColumnaTablero::findOrFail($destino)->categoria;
                $columna->tareas()->get()->each(fn (Tarea $tarea) => $tarea->update(['columna_id' => $destino, 'estado' => $categoria]));
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
        ]);

        return $this->respuesta($request, 'Tarjeta añadida.', [
            'id' => $tarea->id,
            'html' => view('tareas._tarjeta', ['tarea' => $tarea->load('columna'), 'columnasOrden' => ColumnaTablero::ordenadas()->get()])->render(),
        ], 201);
    }

    /** Mueve una tarea a otra columna; su estado pasa a ser el tipo de esa columna. */
    public function moverTarea(MoverTareaColumnaRequest $request, Tarea $tarea): RedirectResponse|JsonResponse
    {
        $tarea->update($request->validated());

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
                $request->session()->flash('estado', $mensaje);
            }

            return response()->json($datos + ['mensaje' => $mensaje], $codigo);
        }

        return redirect()->route('tareas.index', ['vista' => 'tablero'])->with('estado', $mensaje);
    }
}
