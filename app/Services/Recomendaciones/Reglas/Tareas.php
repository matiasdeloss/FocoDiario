<?php

namespace App\Services\Recomendaciones\Reglas;

use App\Enums\TipoRecomendacion;
use App\Services\Recomendaciones\ContextoRecomendacion;
use App\Services\Recomendaciones\Fuente;
use App\Services\Recomendaciones\Recomendacion;
use App\Services\Recomendaciones\Regla;

class Tareas implements Regla
{
    public function evaluar(ContextoRecomendacion $contexto): array
    {
        if ($contexto->tareasVencidas > 0) {
            $n = $contexto->tareasVencidas;

            return [new Recomendacion(
                titulo: $n === 1 ? 'Tenés una tarea vencida' : "Tenés {$n} tareas vencidas",
                mensaje: 'Resolvela, reprogramala o descartala hoy para no arrastrar pendientes.',
                tipo: TipoRecomendacion::Alerta,
                icono: 'bi-exclamation-triangle',
                prioridad: 90,
            )];
        }

        if ($contexto->tareasAbiertas === 0) {
            return [new Recomendacion(
                titulo: 'No tenés tareas abiertas',
                mensaje: 'Planificar el día es un hábito clave: cargá las tareas que querés lograr hoy.',
                tipo: TipoRecomendacion::Sugerencia,
                icono: 'bi-journal-plus',
                prioridad: 68,
                fuente: Fuente::duhigg(),
            )];
        }

        if (! $contexto->hayTareaAltaParaHoy) {
            return [new Recomendacion(
                titulo: 'Elegí la tarea más importante del día',
                mensaje: 'No hay una tarea de prioridad alta para hoy. Elegí una y marcala como alta antes de empezar con el resto.',
                tipo: TipoRecomendacion::Sugerencia,
                icono: 'bi-flag',
                prioridad: 70,
                fuente: Fuente::aeon(),
            )];
        }

        return [];
    }
}
