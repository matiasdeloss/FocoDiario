<?php

namespace App\Services\Recomendaciones\Reglas;

use App\Enums\TipoRecomendacion;
use App\Services\Recomendaciones\ContextoRecomendacion;
use App\Services\Recomendaciones\Recomendacion;
use App\Services\Recomendaciones\Regla;

/** Sugerencia sin fuente verificada: "ocio como recompensa" es una idea de docs/03 sin cita. */
class OcioComoRecompensa implements Regla
{
    public function evaluar(ContextoRecomendacion $contexto): array
    {
        if ($contexto->minutosProductivos < 25 || $contexto->minutosOcio > 0 || $contexto->minutosSeguidosSinPausa > 90) {
            return [];
        }

        return [new Recomendacion(
            titulo: 'Te ganaste un rato de ocio',
            mensaje: 'Cerraste un bloque de foco y todavía no usaste ocio hoy. Usalo como recompensa, con hora de inicio y de fin.',
            tipo: TipoRecomendacion::Sugerencia,
            icono: 'bi-controller',
            prioridad: 45,
        )];
    }
}
