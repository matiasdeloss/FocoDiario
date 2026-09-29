<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Configuración global clave/valor. */
#[Fillable(['clave', 'valor'])]
class Ajuste extends Model
{
    public const TITULO_PLANNER = 'planner.titulo';

    public const TITULO_PLANNER_POR_DEFECTO = 'Planner semanal';

    protected $primaryKey = 'clave';

    public $incrementing = false;

    protected $keyType = 'string';

    public static function obtener(string $clave, ?string $porDefecto = null): ?string
    {
        $valor = static::query()->whereKey($clave)->value('valor');

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
