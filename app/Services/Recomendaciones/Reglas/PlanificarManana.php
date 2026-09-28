<?php

namespace App\Services\Recomendaciones\Reglas;

use App\Enums\TipoRecomendacion;
use App\Services\Recomendaciones\ContextoRecomendacion;
use App\Services\Recomendaciones\Fuente;
use App\Services\Recomendaciones\Recomendacion;
use App\Services\Recomendaciones\Regla;

class PlanificarManana implements Regla
{
    public function evaluar(ContextoRecomendacion $contexto): array
    {
        if ($contexto->minutoDelDia() < 20 * 60) {
            return [];
        }

        return [new Recomendacion(
            titulo: 'Planificá mañana',
            mensaje: 'Antes de dormir, elegí las tareas de mañana y cuál es la más importante. La planificación diaria es un hábito clave que arrastra otros cambios.',
            tipo: TipoRecomendacion::Sugerencia,
            icono: 'bi-calendar-check',
            prioridad: 66,
            fuente: Fuente::duhigg(),
        )];
    }
}
