<?php

namespace App\Services\Recomendaciones\Reglas;

use App\Enums\TipoRecomendacion;
use App\Services\Recomendaciones\ContextoRecomendacion;
use App\Services\Recomendaciones\Fuente;
use App\Services\Recomendaciones\Recomendacion;
use App\Services\Recomendaciones\Regla;

/** Pico, valle y recuperación (Pink). Orientativo: no considera cronotipo y los cortes horarios no tienen fuente independiente. */
class MomentoDelDia implements Regla
{
    public function evaluar(ContextoRecomendacion $contexto): array
    {
        $minuto = $contexto->minutoDelDia();

        [$titulo, $mensaje, $icono] = match (true) {
            $minuto >= 5 * 60 && $minuto < 12 * 60 => ['Momento de pico', 'Suele ser la mejor hora para el trabajo analítico exigente: estudiar, resolver problemas, escribir.', 'bi-brightness-high'],
            $minuto >= 12 * 60 && $minuto < 15 * 60 => ['Momento de valle', 'Suele bajar la alerta y subir los errores: dejá las tareas rutinarias y administrativas para ahora.', 'bi-cloud-sun'],
            $minuto >= 15 * 60 && $minuto < 21 * 60 => ['Momento de recuperación', 'Suele mejorar el ánimo: buen momento para la creatividad y la lluvia de ideas.', 'bi-lightbulb'],
            default => [null, null, null],
        };

        if ($titulo === null) {
            return [];
        }

        return [new Recomendacion(
            titulo: $titulo,
            mensaje: $mensaje.' Es orientativo: no tiene en cuenta tu cronotipo y los cortes horarios no tienen fuente independiente.',
            tipo: TipoRecomendacion::Info,
            icono: $icono,
            prioridad: 50,
            fuente: Fuente::pink(),
        )];
    }
}
