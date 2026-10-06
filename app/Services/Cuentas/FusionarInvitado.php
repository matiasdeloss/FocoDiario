<?php

namespace App\Services\Cuentas;

use App\Enums\EstadoSesion;
use App\Models\Caja;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Un invitado inicia sesión en una cuenta que ya existía: todo lo que hizo como invitado pasa a esa cuenta.
 * Trabaja con el query builder (sin los scopes por usuario) y en una transacción; al final borra al invitado.
 */
class FusionarInvitado
{
    /** Tablas que solo cambian de dueño. Tableros (con sus columnas), contextos, cajas y ajustes tienen su propio tratamiento. */
    private const TABLAS = ['tareas', 'recordatorios', 'notas', 'bloques_tiempo', 'sesiones_estudio', 'intervalos_estudio'];

    public const SUFIJO_CONTEXTO = ' (invitado)';

    public function ejecutar(User $invitado, User $cuenta): void
    {
        if (! $invitado->es_invitado || $invitado->is($cuenta)) {
            return;
        }

        DB::transaction(function () use ($invitado, $cuenta) {
            $de = $invitado->id;
            $a = $cuenta->id;

            $this->tableros($de, $a);
            $this->contextos($de, $a);
            $this->cajas($de, $a);

            // Ajustes: manda la cuenta; los que la cuenta no tiene, se conservan.
            $clavesDeLaCuenta = DB::table('ajustes')->where('user_id', $a)->pluck('clave');
            DB::table('ajustes')->where('user_id', $de)->whereIn('clave', $clavesDeLaCuenta)->delete();
            DB::table('ajustes')->where('user_id', $de)->update(['user_id' => $a]);

            // Una sesión de estudio que el invitado dejó abierta se da por terminada.
            DB::table('sesiones_estudio')->where('user_id', $de)->where('estado', EstadoSesion::EnCurso->value)
                ->update(['estado' => EstadoSesion::Finalizada->value, 'finalizada_en' => now()]);

            foreach (self::TABLAS as $tabla) {
                DB::table($tabla)->where('user_id', $de)->update(['user_id' => $a]);
            }

            $invitado->delete();
        });
    }

    /**
     * Tableros. El principal del invitado se une al principal de la cuenta: "Sin asignar" con "Sin asignar" y las columnas
     * con el mismo nombre y tipo se unen (sus tarjetas pasan a la de la cuenta); las demás se agregan al final.
     * Los otros tableros del invitado pasan a la cuenta como tableros propios (con " (invitado)" si el nombre ya existe) y
     * ninguno es principal: la cuenta sigue con exactamente un principal.
     */
    private function tableros(int $de, int $a): void
    {
        $principalCuenta = DB::table('tableros')->where('user_id', $a)->orderByDesc('principal')->orderBy('posicion')->first();
        $delInvitado = DB::table('tableros')->where('user_id', $de)->orderByDesc('principal')->orderBy('posicion')->orderBy('id')->get();

        // Una cuenta sin tablero (no debería pasar) se queda con los del invitado tal cual.
        if ($principalCuenta === null) {
            DB::table('tableros')->where('user_id', $de)->update(['user_id' => $a]);
            DB::table('columnas_tablero')->where('user_id', $de)->update(['user_id' => $a]);

            return;
        }

        $principalInvitado = $delInvitado->first(fn ($t) => (bool) $t->principal) ?? $delInvitado->first();
        $deLaCuenta = DB::table('columnas_tablero')->where('tablero_id', $principalCuenta->id)->get();
        $posicion = (int) $deLaCuenta->max('posicion');

        if ($principalInvitado !== null) {
            foreach (DB::table('columnas_tablero')->where('tablero_id', $principalInvitado->id)->orderBy('posicion')->orderBy('id')->get() as $columna) {
                $igual = $deLaCuenta->first(fn ($otra) => $columna->fija
                    ? (bool) $otra->fija
                    : ! $otra->fija && $otra->categoria === $columna->categoria && mb_strtolower(trim($otra->nombre)) === mb_strtolower(trim($columna->nombre)));

                if ($igual !== null) {
                    DB::table('tareas')->where('columna_id', $columna->id)->update(['columna_id' => $igual->id]);
                    DB::table('notas')->where('columna_id', $columna->id)->update(['columna_id' => $igual->id]);
                    DB::table('columnas_tablero')->where('id', $columna->id)->delete();
                } else {
                    DB::table('columnas_tablero')->where('id', $columna->id)->update(['user_id' => $a, 'tablero_id' => $principalCuenta->id, 'posicion' => ++$posicion]);
                }
            }

            DB::table('tableros')->where('id', $principalInvitado->id)->delete();
        }

        $nombres = DB::table('tableros')->where('user_id', $a)->pluck('nombre')->map(fn ($n) => mb_strtolower($n))->flip();
        $lugar = (int) DB::table('tableros')->where('user_id', $a)->max('posicion');

        foreach ($delInvitado->reject(fn ($t) => $principalInvitado !== null && $t->id === $principalInvitado->id) as $tablero) {
            $nombre = $tablero->nombre;

            for ($n = 1; isset($nombres[mb_strtolower($nombre)]); $n++) {
                $nombre = $tablero->nombre.self::SUFIJO_CONTEXTO.($n > 1 ? " {$n}" : '');
            }

            $nombres[mb_strtolower($nombre)] = true;

            DB::table('tableros')->where('id', $tablero->id)->update(['user_id' => $a, 'nombre' => $nombre, 'principal' => false, 'posicion' => ++$lugar]);
            DB::table('columnas_tablero')->where('tablero_id', $tablero->id)->update(['user_id' => $a]);
        }
    }

    /**
     * Solo pueden chocar los contextos raíz (los hijos cuelgan de padres del invitado, que se mudan con ellos):
     * si la cuenta ya tiene uno con ese nombre, el del invitado se renombra con "(invitado)".
     */
    private function contextos(int $de, int $a): void
    {
        $nombres = DB::table('contextos')->where('user_id', $a)->whereNull('contexto_padre_id')
            ->pluck('nombre')->map(fn ($n) => mb_strtolower($n))->flip();

        foreach (DB::table('contextos')->where('user_id', $de)->whereNull('contexto_padre_id')->get() as $contexto) {
            $nombre = $contexto->nombre;

            for ($n = 1; isset($nombres[mb_strtolower($nombre)]); $n++) {
                $nombre = $contexto->nombre.self::SUFIJO_CONTEXTO.($n > 1 ? " {$n}" : '');
            }

            $nombres[mb_strtolower($nombre)] = true;

            if ($nombre !== $contexto->nombre) {
                DB::table('contextos')->where('id', $contexto->id)->update(['nombre' => $nombre]);
            }
        }

        DB::table('contextos')->where('user_id', $de)->update(['user_id' => $a]);
    }

    /**
     * Hoja del día: las cajas del invitado van debajo de las de la cuenta ese día.
     * Notas y Pendiente de la semana (una por semana y zona): si la cuenta ya tiene la suya, se le suma lo del invitado.
     */
    private function cajas(int $de, int $a): void
    {
        foreach (DB::table('cajas')->where('user_id', $de)->whereNotNull('fecha')->distinct()->pluck('fecha') as $fecha) {
            $debajo = (int) DB::table('cajas')->where('user_id', $a)->where('fecha', $fecha)->max(DB::raw('y + alto'));

            if ($debajo === 0) {
                continue;
            }

            foreach (DB::table('cajas')->where('user_id', $de)->where('fecha', $fecha)->get(['id', 'y']) as $caja) {
                DB::table('cajas')->where('id', $caja->id)->update(['y' => min((int) $caja->y + $debajo, Caja::MAX_FILA)]);
            }
        }

        foreach (DB::table('cajas')->where('user_id', $de)->whereNotNull('semana')->get() as $caja) {
            $suya = DB::table('cajas')->where('user_id', $a)->where('semana', $caja->semana)->where('zona', $caja->zona)->first();

            if ($suya === null) {
                continue;
            }

            $items = array_merge(json_decode($suya->items ?? '[]', true) ?: [], json_decode($caja->items ?? '[]', true) ?: []);

            DB::table('cajas')->where('id', $suya->id)->update([
                'contenido' => trim(implode("\n\n", array_filter([$suya->contenido, $caja->contenido], fn ($t) => filled($t)))) ?: null,
                'items' => $items === [] ? $suya->items : json_encode($items, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
            DB::table('cajas')->where('id', $caja->id)->delete();
        }

        DB::table('cajas')->where('user_id', $de)->update(['user_id' => $a]);
    }
}
