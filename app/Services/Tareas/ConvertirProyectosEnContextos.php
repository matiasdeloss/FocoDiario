<?php

namespace App\Services\Tareas;

use App\Enums\TipoContexto;
use Illuminate\Support\Facades\DB;

/**
 * Convierte el texto libre `tareas.proyecto` en contextos: cada nombre distinto de cada usuario pasa a ser
 * un contexto (el que ya tenga con ese nombre, sin distinguir mayúsculas, o uno nuevo de tipo proyecto)
 * y la tarea queda apuntando a él en `contexto_id`. No borra la columna `proyecto`.
 *
 * Usa el query builder (sin los scopes por usuario): corre dentro de una migración, sin sesión.
 */
class ConvertirProyectosEnContextos
{
    /** @return array{creados: int, vinculadas: int} */
    public function __invoke(): array
    {
        $creados = 0;
        $vinculadas = 0;
        $contextosPorUsuario = [];
        $grupos = [];

        foreach (DB::table('tareas')->whereNotNull('proyecto')->orderBy('id')->get(['id', 'user_id', 'proyecto']) as $tarea) {
            $nombre = trim((string) $tarea->proyecto);

            if ($nombre === '') {
                continue;
            }

            $clave = $tarea->user_id.'|'.mb_strtolower($nombre);
            $grupos[$clave] ??= ['usuario' => $tarea->user_id, 'nombre' => $nombre, 'ids' => []];
            $grupos[$clave]['ids'][] = $tarea->id;
        }

        foreach ($grupos as $grupo) {
            ['usuario' => $usuario, 'nombre' => $nombre, 'ids' => $ids] = $grupo;

            $contextosPorUsuario[$usuario] ??= DB::table('contextos')->where('user_id', $usuario)->orderBy('id')->get(['id', 'nombre', 'contexto_padre_id']);
            $coincidencias = $contextosPorUsuario[$usuario]->filter(fn ($c) => mb_strtolower(trim($c->nombre)) === mb_strtolower($nombre));
            // Prefiere uno de primer nivel; si no, el primero que haya.
            $existente = $coincidencias->first(fn ($c) => $c->contexto_padre_id === null) ?? $coincidencias->first();

            if ($existente !== null) {
                $contextoId = $existente->id;
            } else {
                $contextoId = DB::table('contextos')->insertGetId([
                    'user_id' => $usuario,
                    'nombre' => $nombre,
                    'tipo' => TipoContexto::Proyecto->value,
                    'contexto_padre_id' => null,
                    'color' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $contextosPorUsuario[$usuario]->push((object) ['id' => $contextoId, 'nombre' => $nombre, 'contexto_padre_id' => null]);
                $creados++;
            }

            foreach (array_chunk($ids, 500) as $lote) {
                $vinculadas += DB::table('tareas')->whereIn('id', $lote)->update(['contexto_id' => $contextoId]);
            }
        }

        return ['creados' => $creados, 'vinculadas' => $vinculadas];
    }
}
