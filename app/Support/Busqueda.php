<?php

namespace App\Support;

/**
 * Búsqueda de texto sin distinguir mayúsculas, igual en SQLite, MySQL y PostgreSQL (en PostgreSQL `like` sí las distingue).
 * Uso: `->whereRaw(Busqueda::condicion('titulo'), [Busqueda::patron($texto)])`.
 */
final class Busqueda
{
    private const ESCAPE = '!';

    /** Patrón "contiene" con los comodines del texto (% y _) escapados. */
    public static function patron(string $texto): string
    {
        return '%'.strtr($texto, [self::ESCAPE => self::ESCAPE.self::ESCAPE, '%' => self::ESCAPE.'%', '_' => self::ESCAPE.'_']).'%';
    }

    /** Condición SQL para una columna (con su tabla si hace falta); lleva un `?` con el patrón de patron(). */
    public static function condicion(string $columna): string
    {
        return "LOWER({$columna}) LIKE LOWER(?) ESCAPE '".self::ESCAPE."'";
    }
}
