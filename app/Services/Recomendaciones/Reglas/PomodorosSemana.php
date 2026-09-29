<?php

namespace App\Services\Recomendaciones\Reglas;

use App\Enums\TipoRecomendacion;
use App\Services\Recomendaciones\ContextoRecomendacion;
use App\Services\Recomendaciones\Recomendacion;
use App\Services\Recomendaciones\Regla;

/** Cuenta los pomodoros de foco completados esta semana (lunes a domingo). */
class PomodorosSemana implements Regla
{
    public function evaluar(ContextoRecomendacion $contexto): array
    {
        if ($contexto->pomodorosSemana > 0) {
            $n = $contexto->pomodorosSemana;

            return [new Recomendacion(
                titulo: $n === 1 ? '1 pomodoro esta semana' : "$n pomodoros esta semana",
                mensaje: 'Completaste bloques de foco en Estudio. Seguí alternando foco y descanso para sostener el ritmo.',
                tipo: TipoRecomendacion::Logro,
                icono: 'bi-hourglass-split',
                prioridad: 30,
                datos: ['Pomodoros de la semana' => (string) $n],
            )];
        }

        return [new Recomendacion(
            titulo: 'Probá un pomodoro esta semana',
            mensaje: 'Todavía no completaste ninguno. Un bloque de 25 minutos en Estudio es una buena forma de arrancar.',
            tipo: TipoRecomendacion::Sugerencia,
            icono: 'bi-hourglass-split',
            prioridad: 35,
        )];
    }
}
