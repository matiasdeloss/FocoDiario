<?php

namespace App\Services\Recomendaciones\Reglas;

use App\Enums\TipoRecomendacion;
use App\Services\Recomendaciones\ContextoRecomendacion;
use App\Services\Recomendaciones\Fuente;
use App\Services\Recomendaciones\Recomendacion;
use App\Services\Recomendaciones\Regla;

/** El ejercicio como hábito clave viene de Duhigg; se detecta por una categoría con "ejercicio" en el nombre. */
class Ejercicio implements Regla
{
    public function evaluar(ContextoRecomendacion $contexto): array
    {
        if ($contexto->hizoEjercicioHoy || ! $contexto->esDeDia()) {
            return [];
        }

        return [new Recomendacion(
            titulo: 'Movete un rato',
            mensaje: 'Hoy no registraste ejercicio. Hacer ejercicio es un hábito clave que refuerza la fuerza de voluntad. Para que el sistema lo detecte, usá una categoría con "ejercicio" en el nombre.',
            tipo: TipoRecomendacion::Sugerencia,
            icono: 'bi-bicycle',
            prioridad: 52,
            fuente: Fuente::duhigg(),
        )];
    }
}
