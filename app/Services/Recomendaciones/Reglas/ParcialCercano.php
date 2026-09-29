<?php

namespace App\Services\Recomendaciones\Reglas;

use App\Enums\TipoRecomendacion;
use App\Services\Recomendaciones\ContextoRecomendacion;
use App\Services\Recomendaciones\Fuente;
use App\Services\Recomendaciones\Recomendacion;
use App\Services\Recomendaciones\Regla;

/** Una tarea abierta con "parcial", "examen" o "prueba" en el título y fecha en los próximos 7 días. */
class ParcialCercano implements Regla
{
    public function evaluar(ContextoRecomendacion $contexto): array
    {
        $tarea = $contexto->parcialProximo;
        if (! $tarea) {
            return [];
        }

        $dias = (int) $contexto->ahora->startOfDay()->diffInDays($tarea->fecha_limite->startOfDay());
        $cuando = match (true) {
            $dias === 0 => 'es hoy',
            $dias === 1 => 'es mañana',
            default => "es en $dias días",
        };

        return [new Recomendacion(
            titulo: 'Se acerca: '.$tarea->titulo,
            mensaje: "Tu evaluación $cuando. Practicá recordando sin mirar los apuntes, repartí el repaso en días distintos y cuidá tus 7 a 9 horas de sueño, sobre todo la noche anterior.",
            tipo: $dias <= 2 ? TipoRecomendacion::Alerta : TipoRecomendacion::Sugerencia,
            icono: 'bi-mortarboard',
            prioridad: $dias <= 2 ? 75 : 58,
            fuente: Fuente::memoriaYSueno(),
        )];
    }
}
