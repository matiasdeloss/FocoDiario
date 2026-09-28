<?php

namespace App\Services\Recomendaciones\Reglas;

use App\Enums\TipoRecomendacion;
use App\Services\Recomendaciones\ContextoRecomendacion;
use App\Services\Recomendaciones\Fuente;
use App\Services\Recomendaciones\Recomendacion;
use App\Services\Recomendaciones\Regla;

class RepasoEspaciado implements Regla
{
    public function evaluar(ContextoRecomendacion $contexto): array
    {
        if ($contexto->minutosProductivos < 25) {
            return [];
        }

        return [new Recomendacion(
            titulo: 'Programá el repaso de lo que estudiaste',
            mensaje: 'Repasar en intervalos crecientes y practicar recordando con los apuntes cerrados rinde más que releer. Creá un recordatorio de repaso para dentro de unos días.',
            tipo: TipoRecomendacion::Sugerencia,
            icono: 'bi-arrow-repeat',
            prioridad: 48,
            fuente: Fuente::dunlosky(),
        )];
    }
}
