<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Notas, tareas y contextos usan la misma paleta de 10 colores (App\Enums\ColorActividad), guardada como el hex
 * del acento (el formato que ya tenía contextos.color). Las 5 claves viejas de las notas y las tareas se pasan al
 * color más parecido de la paleta nueva:
 *
 *   durazno   (durazno claro)        -> rosa      #c0677a
 *   terracota (naranja terroso)      -> terracota #c0663a
 *   salvia    (verde grisáceo)       -> salvia    #728a58
 *   oliva     (verde amarillento)    -> oliva     #78802a
 *   arena     (beige neutro)         -> arena     #9a8350
 *
 * Cualquier otro valor (vacío o desconocido) queda sin color. Al revertir, los 5 anteriores vuelven a su clave y los
 * colores nuevos pasan al más cercano de los 5 viejos (aproximado: ocre -> durazno, azul polvo, lavanda -> arena,
 * ciruela -> terracota, celeste -> salvia).
 */
return new class extends Migration
{
    private const TABLAS = ['notas', 'tareas'];

    private const IDA = [
        'durazno' => '#c0677a',
        'terracota' => '#c0663a',
        'salvia' => '#728a58',
        'oliva' => '#78802a',
        'arena' => '#9a8350',
    ];

    private const VUELTA = [
        '#c0677a' => 'durazno',
        '#c0663a' => 'terracota',
        '#728a58' => 'salvia',
        '#78802a' => 'oliva',
        '#9a8350' => 'arena',
        '#a97f1c' => 'durazno',
        '#5f86a3' => 'arena',
        '#8e4f73' => 'terracota',
        '#3b8e9b' => 'salvia',
        '#8378b5' => 'arena',
    ];

    public function up(): void
    {
        foreach (self::TABLAS as $tabla) {
            foreach (self::IDA as $clave => $hex) {
                DB::table($tabla)->where('color', $clave)->update(['color' => $hex]);
            }

            DB::table($tabla)->whereNotNull('color')->whereNotIn('color', array_keys(self::VUELTA))->update(['color' => null]);
        }
    }

    public function down(): void
    {
        foreach (self::TABLAS as $tabla) {
            foreach (self::VUELTA as $hex => $clave) {
                DB::table($tabla)->where('color', $hex)->update(['color' => $clave]);
            }

            DB::table($tabla)->whereNotNull('color')->whereNotIn('color', array_values(self::VUELTA))->update(['color' => null]);
        }
    }
};
