<?php

namespace App\Services\Recomendaciones\Reglas;

use App\Enums\TipoRecomendacion;
use App\Services\Recomendaciones\ContextoRecomendacion;
use App\Services\Recomendaciones\Fuente;
use App\Services\Recomendaciones\Recomendacion;
use App\Services\Recomendaciones\Regla;
use App\Support\Duracion;

class FocoDelDia implements Regla
{
    public function evaluar(ContextoRecomendacion $contexto): array
    {
        if (! $contexto->esDeDia()) {
            return [];
        }

        $minutos = $contexto->minutosProductivos;

        if ($minutos > 240) {
            return [new Recomendacion(
                titulo: 'Ya superaste las 4 horas de foco',
                mensaje: 'Llevás '.Duracion::formatear($minutos).' de foco productivo. Descansá: los datos de referencia hablan de 3,5 a 4 horas diarias como máximo sostenido.',
                tipo: TipoRecomendacion::Sugerencia,
                icono: 'bi-cup-hot',
                prioridad: 70,
                fuente: Fuente::ericsson(),
            )];
        }

        // Sin nada registrado pasadas las 10:00 se encarga la regla de sin actividad.
        if ($minutos >= 180 || ($minutos === 0 && $contexto->minutoDelDia() >= 10 * 60)) {
            return [];
        }

        return [new Recomendacion(
            titulo: 'Apuntá a 3 o 4 horas de foco',
            mensaje: 'Llevás '.Duracion::formatear($minutos).' de foco productivo hoy. Como punto de partida, completá entre 3 y 4 horas en bloques de 50 a 90 minutos con descansos. Es un dato de músicos de élite: ajustalo con tus propios datos.',
            tipo: TipoRecomendacion::Sugerencia,
            icono: 'bi-bullseye',
            prioridad: 60,
            fuente: Fuente::ericsson(),
        )];
    }
}
