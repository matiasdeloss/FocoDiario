<?php

namespace App\Services\Recomendaciones\Reglas;

use App\Enums\TipoRecomendacion;
use App\Services\Recomendaciones\ContextoRecomendacion;
use App\Services\Recomendaciones\Fuente;
use App\Services\Recomendaciones\Recomendacion;
use App\Services\Recomendaciones\Regla;
use App\Support\Duracion;

class Descansos implements Regla
{
    public function evaluar(ContextoRecomendacion $contexto): array
    {
        if ($contexto->minutosSeguidosSinPausa <= 90) {
            return [];
        }

        return [new Recomendacion(
            titulo: 'Hacé una pausa',
            mensaje: 'Llevás '.Duracion::formatear($contexto->minutosSeguidosSinPausa).' seguidos sin pausa. Descansá entre 5 y 15 minutos: movete, salí al aire libre y dejá el celular. El efecto de los microdescansos es pequeño en vigor y fatiga, y para tareas muy exigentes conviene una pausa más larga.',
            tipo: TipoRecomendacion::Sugerencia,
            icono: 'bi-cup-hot',
            prioridad: 85,
            fuente: Fuente::albulescu(),
        )];
    }
}
