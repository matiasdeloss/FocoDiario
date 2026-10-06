<?php

namespace App\Http\Controllers;

use App\Enums\EstadoTarea;
use App\Http\Requests\EliminarTableroRequest;
use App\Http\Requests\TableroRequest;
use App\Models\ColumnaTablero;
use App\Models\Nota;
use App\Models\Tablero;
use App\Models\Tarea;
use App\Services\Cuentas\DatosIniciales;
use App\Support\Aviso;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Gestión de los tableros del usuario: crear (con "Sin asignar" y las columnas de fábrica), renombrar, elegir el
 * principal y eliminar. Siempre hay exactamente un tablero principal y nunca se elimina el último.
 */
class TableroGestionController extends Controller
{
    public function store(TableroRequest $request): RedirectResponse
    {
        $primero = Tablero::query()->doesntExist();

        $tablero = Tablero::crearConColumnas(
            Auth::id(),
            $request->validated('nombre'),
            $primero,
            array_map(fn ($c) => [$c['nombre'], $c['categoria']], DatosIniciales::COLUMNAS),
            (int) Tablero::max('posicion') + 1,
        );

        return redirect()->route('tablero.index', ['tablero' => $tablero->id])->with(Aviso::flash('Tablero creado.'));
    }

    public function update(TableroRequest $request, Tablero $tablero): RedirectResponse
    {
        $tablero->update($request->validated());

        return redirect()->route('tablero.index', ['tablero' => $tablero->id])->with(Aviso::flash('Tablero actualizado.'));
    }

    public function principal(Tablero $tablero): RedirectResponse
    {
        $tablero->hacerPrincipal();

        return redirect()->route('tablero.index', ['tablero' => $tablero->id])
            ->with(Aviso::flash('"'.$tablero->nombre.'" es ahora el tablero principal: lo que crees fuera del tablero va a su columna "Sin asignar".'));
    }

    /**
     * Elimina el tablero y pasa todas sus tarjetas (tareas y notas) a la columna "Sin asignar" del tablero de destino.
     * Si era el principal, el de destino pasa a serlo.
     */
    public function destroy(EliminarTableroRequest $request, Tablero $tablero): RedirectResponse
    {
        $destino = Tablero::findOrFail($request->validated('destino_id'));

        DB::transaction(function () use ($tablero, $destino) {
            $sinAsignar = ColumnaTablero::sinAsignarDe($destino->id);
            $columnas = ColumnaTablero::where('tablero_id', $tablero->id)->pluck('id');

            // Las tareas quedan pendientes (así es "Sin asignar"); lo que estaba hecho también vuelve a empezar.
            Tarea::whereIn('columna_id', $columnas)->update(['columna_id' => $sinAsignar->id, 'estado' => EstadoTarea::Pendiente]);
            Nota::whereIn('columna_id', $columnas)->update(['columna_id' => $sinAsignar->id]);

            if ($tablero->principal) {
                $destino->hacerPrincipal();
            }

            $tablero->delete();
        });

        return redirect()->route('tablero.index', ['tablero' => $destino->id])->with(Aviso::flash('Tablero eliminado. Sus tarjetas pasaron a "Sin asignar" de "'.$destino->nombre.'".'));
    }
}
