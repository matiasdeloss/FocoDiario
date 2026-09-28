<?php

namespace App\Services\Recomendaciones\Reglas;

use App\Enums\TipoRecomendacion;
use App\Services\Recomendaciones\ContextoRecomendacion;
use App\Services\Recomendaciones\Recomendacion;
use App\Services\Recomendaciones\Regla;

/** Sugerencia sin fuente: el respaldo de la revisión semanal está pendiente de verificar (docs/05). */
class RevisionSemanal implements Regla
{
    public function evaluar(ContextoRecomendacion $contexto): array
    {
        if (! $contexto->ahora->isSunday()) {
            return [];
        }

        return [new Recomendacion(
            titulo: 'Revisión semanal',
            mensaje: 'Es domingo: dedicá 5 minutos a ver qué cumpliste, qué quedó pendiente y qué vas a ajustar la semana próxima.',
            tipo: TipoRecomendacion::Sugerencia,
            icono: 'bi-calendar-week',
            prioridad: 58,
        )];
    }
}
