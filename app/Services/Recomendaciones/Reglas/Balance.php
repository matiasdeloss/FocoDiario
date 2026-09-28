<?php

namespace App\Services\Recomendaciones\Reglas;

use App\Enums\TipoRecomendacion;
use App\Services\Recomendaciones\ContextoRecomendacion;
use App\Services\Recomendaciones\Recomendacion;
use App\Services\Recomendaciones\Regla;
use App\Support\Duracion;

/** Sugerencia sin fuente verificada: el ocio con horario es una idea de docs/03 sin cita. */
class Balance implements Regla
{
    public function evaluar(ContextoRecomendacion $contexto): array
    {
        if ($contexto->minutosOcio === 0 || $contexto->minutosOcio <= $contexto->minutosProductivos) {
            return [];
        }

        return [new Recomendacion(
            titulo: 'Hoy hay más ocio que foco',
            mensaje: 'Llevás '.Duracion::formatear($contexto->minutosOcio).' de ocio y '.Duracion::formatear($contexto->minutosProductivos).' productivas. Hacé un bloque de foco antes de más ocio y, si el ocio no estaba previsto, ponele horario y un tope.',
            tipo: TipoRecomendacion::Sugerencia,
            icono: 'bi-arrow-left-right',
            prioridad: 65,
        )];
    }
}
