<?php

namespace App\Services\Recomendaciones\Reglas;

use App\Enums\TipoRecomendacion;
use App\Services\Recomendaciones\ContextoRecomendacion;
use App\Services\Recomendaciones\Fuente;
use App\Services\Recomendaciones\Recomendacion;
use App\Services\Recomendaciones\Regla;

class IntencionDeImplementacion implements Regla
{
    public function evaluar(ContextoRecomendacion $contexto): array
    {
        if ($contexto->tareasAbiertas === 0 || $contexto->minutosProductivos >= 180 || ! $contexto->esDeDia()) {
            return [];
        }

        return [new Recomendacion(
            titulo: 'Escribí un "Cuando..., haré..."',
            mensaje: 'Definí una señal concreta para empezar, por ejemplo: "Cuando termine de almorzar, haré 25 minutos de estudio". Atar la tarea a una situación ayuda a cumplirla.',
            tipo: TipoRecomendacion::Sugerencia,
            icono: 'bi-signpost-split',
            prioridad: 42,
            fuente: Fuente::gollwitzer(),
        )];
    }
}
