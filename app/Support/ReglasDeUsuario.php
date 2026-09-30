<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

/**
 * Reglas exists/unique atadas al usuario de la sesión. Las reglas de Laravel consultan la tabla directo
 * (sin el scope de PerteneceAUsuario): sin esto se podría apuntar a un contexto o una tarea de otra persona.
 * Sin sesión, Auth::id() es null y la regla no encuentra nada (falla cerrado).
 */
final class ReglasDeUsuario
{
    public static function existe(string $tabla, string $columna = 'id'): Exists
    {
        return Rule::exists($tabla, $columna)->where('user_id', Auth::id());
    }

    public static function unico(string $tabla, string $columna): Unique
    {
        return Rule::unique($tabla, $columna)->where('user_id', Auth::id());
    }
}
