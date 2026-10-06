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
use App\Models\Nota;
use App\Models\Tablero;
use App\Models\Tarea;
use App\Support\Aviso;
use App\Support\ColoresDeContexto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Columnas personalizables de un tablero y movimiento de tarjetas (tareas y notas) entre ellas.
 * "Sin asignar" es la columna fija de cada tablero: primera, sin renombrar, mover ni eliminar.
 */
class ColumnaTableroController extends Controller
{
    public function store(ColumnaTableroRequest $request): RedirectResponse|JsonResponse
    {
        $tableroId = $request->validated('tablero_id') ?? Tablero::principal()?->id;

        $columna = ColumnaTablero::create([
            'nombre' => $request->validated('nombre'),
            'categoria' => $request->validated('categoria') ?? EstadoTarea::EnProgreso,
            'posicion' => (int) ColumnaTablero::where('tablero_id', $tableroId)->max('posicion') + 1,
            'tablero_id' => $tableroId,
        ]);

        return $this->respuesta($request, 'Columna creada.', ['columna' => $columna], 201, $tableroId);
    }

    public function update(ColumnaTableroRequest $request, ColumnaTablero $columna): RedirectResponse|JsonResponse
    {
        $columna->update($request->safe()->except("tablero_id"));

        // Si cambió el tipo, las tareas de la columna adoptan el estado nuevo (una sola consulta).
        if ($columna->wasChanged('categoria')) {
            $columna->tareas()->update(['estado' => $columna->categoria]);
        }

        return $this->respuesta($request, 'Columna actualizada.', ['columna' => $columna], 200, $columna->tablero_id);
    }

    /** Elimina la columna y pasa sus tarjetas (tareas y notas) a la columna elegida; las tareas ajustan su estado al de destino. */
    public function destroy(EliminarColumnaTableroRequest $request, ColumnaTablero $columna): RedirectResponse|JsonResponse
    {
        DB::transaction(function () use ($request, $columna) {
            $destino = $request->validated('reasignar_a');

            if ($destino !== null) {
                $categoria = ColumnaTablero::findOrFail($destino)->categoria;
                $columna->tareas()->update(['columna_id' => $destino, 'estado' => $categoria]);
                $columna->notas()->update(['columna_id' => $destino]);
            }

            $columna->delete();
        });

        return $this->respuesta($request, 'Columna eliminada.', [], 200, $columna->tablero_id);
    }

    public function mover(MoverColumnaTableroRequest $request, ColumnaTablero $columna): RedirectResponse|JsonResponse
    {
        abort_if($columna->fija, 422, 'La columna "Sin asignar" siempre va primera.');

        $columnas = ColumnaTablero::ordenadas()->where('tablero_id', $columna->tablero_id)->get(['id', 'fija']);
        $orden = $columnas->pluck('id')->all();
        $indice = array_search($columna->id, $orden, true);
        $destino = $indice + ($request->validated('direccion') === 'izquierda' ? -1 : 1);

        // La columna fija no se corre: nada pasa a su izquierda.
        if (isset($orden[$destino]) && ! $columnas[$destino]->fija) {
            [$orden[$indice], $orden[$destino]] = [$orden[$destino], $orden[$indice]];

            DB::transaction(function () use ($orden) {
                foreach ($orden as $posicion => $id) {
                    ColumnaTablero::whereKey($id)->update(['posicion' => $posicion]);
                }
            });
        }

        return $this->respuesta($request, 'Columna movida.', ['orden' => $orden], 200, $columna->tablero_id);
    }

    /** Alta rápida de una tarjeta al pie de una columna: solo título (una tarea, o una nota si se pide). */
    public function tarjeta(TarjetaColumnaRequest $request, ColumnaTablero $columna): RedirectResponse|JsonResponse
    {
        $orden = $this->siguienteOrden($columna->id);
        $columnasOrden = ColumnaTablero::ordenadas()->where('tablero_id', $columna->tablero_id)->get();
        $colores = ColoresDeContexto::delUsuario();

        if ($request->validated('tipo') === 'nota') {
            $nota = Nota::create([
                'titulo' => $request->validated('titulo'),
                'contenido' => '',
                'columna_id' => $columna->id,
                'orden' => $orden,
            ]);

            return $this->respuesta($request, 'Nota añadida.', [
                'id' => $nota->id,
                'html' => view('tablero._tarjeta-nota', ['nota' => $nota->load('contexto'), 'columnasOrden' => $columnasOrden, 'colores' => $colores])->render(),
            ], 201, $columna->tablero_id);
        }

        $tarea = Tarea::create([
            'titulo' => $request->validated('titulo'),
            'prioridad' => PrioridadTarea::Media,
            'columna_id' => $columna->id,
            'orden' => $orden,
        ]);

        return $this->respuesta($request, 'Tarjeta añadida.', [
            'id' => $tarea->id,
            'html' => view('tablero._tarjeta', ['tarea' => $tarea->load(['columna', 'contexto', 'notas']), 'columnasOrden' => $columnasOrden, 'colores' => $colores])->render(),
        ], 201, $columna->tablero_id);
    }

    /**
     * Mueve una tarea a otra columna (su estado pasa a ser el tipo de esa columna) y, si llega el orden,
     * deja las tarjetas de la columna en ese orden. Sin orden, la tarjeta queda arriba de la columna de destino.
     */
    public function moverTarea(MoverTareaColumnaRequest $request, Tarea $tarea): RedirectResponse|JsonResponse
    {
        $destino = (int) $request->validated('columna_id');
        $cambia = $tarea->columna_id !== $destino;
        $ordenada = $request->validated('tarjetas') !== null || $request->validated('orden') !== null;

        DB::transaction(function () use ($request, $tarea, $destino, $cambia, $ordenada) {
            if ($cambia) {
                $tarea->update([
                    'columna_id' => $destino,
                    'orden' => $ordenada ? $tarea->orden : $this->primerOrden($destino),
                ]);
            }

            $this->aplicarOrden($destino, $request->validated('tarjetas'), $request->validated('orden'));
        });

        return $this->respuesta($request, 'Tarea movida.', [
            'id' => $tarea->id,
            'columna_id' => $tarea->columna_id,
            'estado' => $tarea->estado->value,
        ]);
    }

    /** Lo mismo para una nota: cambia de columna (una nota completada es la que está en una columna de ese tipo). */
    public function moverNota(MoverTareaColumnaRequest $request, Nota $nota): RedirectResponse|JsonResponse
    {
        $destino = (int) $request->validated('columna_id');
        $ordenada = $request->validated('tarjetas') !== null || $request->validated('orden') !== null;

        DB::transaction(function () use ($request, $nota, $destino, $ordenada) {
            if ($nota->columna_id !== $destino) {
                $nota->update(['columna_id' => $destino, 'orden' => $ordenada ? $nota->orden : $this->primerOrden($destino)]);
            }

            $this->aplicarOrden($destino, $request->validated('tarjetas'), $request->validated('orden'));
        });

        return $this->respuesta($request, 'Nota movida.', [
            'id' => $nota->id,
            'columna_id' => $nota->columna_id,
            'completada' => $nota->load('columna')->estaCompletada(),
        ]);
    }

    /** Orden que deja una tarjeta nueva al final de la columna (entre tareas y notas). */
    private function siguienteOrden(int $columna): int
    {
        return max((int) Tarea::where('columna_id', $columna)->max('orden'), (int) Nota::where('columna_id', $columna)->max('orden')) + 1;
    }

    /** Orden que deja una tarjeta movida arriba de todo en la columna de destino. */
    private function primerOrden(int $columna): int
    {
        return min((int) Tarea::where('columna_id', $columna)->min('orden'), (int) Nota::where('columna_id', $columna)->min('orden')) - 1;
    }

    /**
     * Guarda el orden de las tarjetas de la columna: `$tarjetas` son "tarea:12" / "nota:5" (notas y tareas juntas);
     * `$ordenTareas` es el formato anterior, solo con ids de tareas.
     *
     * @param  list<string>|null  $tarjetas
     * @param  list<int>|null  $ordenTareas
     */
    private function aplicarOrden(int $columna, ?array $tarjetas, ?array $ordenTareas): void
    {
        $tarjetas ??= array_map(fn ($id) => 'tarea:'.$id, array_values($ordenTareas ?? []));

        foreach ($tarjetas as $posicion => $token) {
            [$tipo, $id] = explode(':', $token);
            $modelo = $tipo === 'nota' ? Nota::class : Tarea::class;

            $modelo::whereKey((int) $id)->where('columna_id', $columna)->update(['orden' => $posicion + 1]);
        }
    }

    private function respuesta(Request $request, string $mensaje, array $datos = [], int $codigo = 200, ?int $tableroId = null): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            // Los modales recargan la página tras guardar: el aviso viaja en la sesión.
            if ($request->hasHeader('X-Modal')) {
                Aviso::guardar($mensaje);
            }

            return response()->json($datos + ['mensaje' => $mensaje], $codigo);
        }

        return redirect()->route('tablero.index', array_filter(['tablero' => $tableroId]))->with(Aviso::flash($mensaje));
    }
}
