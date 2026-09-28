<?php

namespace App\Services\Recomendaciones\Reglas;

use App\Enums\TipoRecomendacion;
use App\Services\Recomendaciones\ContextoRecomendacion;
use App\Services\Recomendaciones\Recomendacion;
use App\Services\Recomendaciones\Regla;

/** Sugerencia sin fuente verificada: el Pomodoro de 25 min está en docs/03 sin cita. */
class SinActividad implements Regla
{
    public function evaluar(ContextoRecomendacion $contexto): array
    {
        $minuto = $contexto->minutoDelDia();

        if ($contexto->bloquesHoy->isNotEmpty() || $minuto < 10 * 60 || $minuto >= 21 * 60) {
            return [];
        }

        return [new Recomendacion(
            titulo: 'Arrancá con un bloque corto',
            mensaje: 'Todavía no registraste nada hoy. Empezá con un bloque de 25 minutos en una sola tarea; después decidís si seguís.',
            tipo: TipoRecomendacion::Sugerencia,
            icono: 'bi-play-circle',
            prioridad: 75,
        )];
    }
}
