<?php

namespace App\Services\Recomendaciones\Reglas;

use App\Enums\TipoRecomendacion;
use App\Services\Recomendaciones\ContextoRecomendacion;
use App\Services\Recomendaciones\Fuente;
use App\Services\Recomendaciones\Recomendacion;
use App\Services\Recomendaciones\Regla;

/** Pequeñas victorias (Duhigg): reconocerlas refuerza la recompensa del hábito. */
class Logros implements Regla
{
    public function evaluar(ContextoRecomendacion $contexto): array
    {
        $recomendaciones = [];

        if ($contexto->tareasCompletadasHoy > 0) {
            $n = $contexto->tareasCompletadasHoy;
            $recomendaciones[] = new Recomendacion(
                titulo: $n === 1 ? 'Completaste una tarea hoy' : "Completaste {$n} tareas hoy",
                mensaje: 'Las pequeñas victorias cuentan: reconocelas.',
                tipo: TipoRecomendacion::Logro,
                icono: 'bi-check2-circle',
                prioridad: 55,
                fuente: Fuente::duhigg(),
            );
        }

        if ($contexto->minutosProductivos >= 180) {
            $recomendaciones[] = new Recomendacion(
                titulo: 'Llegaste a las 3 horas de foco',
                mensaje: 'Cumpliste el mínimo del punto de partida de 3 a 4 horas de foco. Buen día.',
                tipo: TipoRecomendacion::Logro,
                icono: 'bi-trophy',
                prioridad: 56,
                fuente: Fuente::duhigg(),
            );
        }

        return $recomendaciones;
    }
}
