<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAUsuario;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Configuración clave/valor de cada usuario (p. ej. el título del planner). */
#[Fillable(['clave', 'valor'])]
class Ajuste extends Model
{
    use PerteneceAUsuario;

    public const TITULO_PLANNER = 'planner.titulo';

    public const TITULO_PLANNER_POR_DEFECTO = 'Planner semanal';

    public static function obtener(string $clave, ?string $porDefecto = null): ?string
    {
        $valor = static::query()->where('clave', $clave)->value('valor');

        return $valor === null || $valor === '' ? $porDefecto : $valor;
    }

    public static function guardar(string $clave, ?string $valor): void
    {
        static::query()->updateOrCreate(['clave' => $clave], ['valor' => $valor]);
    }

    public static function tituloPlanner(): string
    {
        return static::obtener(self::TITULO_PLANNER, self::TITULO_PLANNER_POR_DEFECTO);
    }
}
