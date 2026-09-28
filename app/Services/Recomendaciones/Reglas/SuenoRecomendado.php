<?php

namespace App\Services\Recomendaciones\Reglas;

use App\Enums\TipoRecomendacion;
use App\Services\Recomendaciones\ContextoRecomendacion;
use App\Services\Recomendaciones\Fuente;
use App\Services\Recomendaciones\Recomendacion;
use App\Services\Recomendaciones\Regla;

/**
 * El horario de sueño no se registra: el sistema lo recomienda a partir de la hora
 * en que suele arrancar el día (mediana del primer bloque de los últimos 7 días, o 07:00).
 */
class SuenoRecomendado implements Regla
{
    private const ARRANQUE_POR_DEFECTO = 7 * 60;

    private const HORAS_OBJETIVO = 8;

    public function evaluar(ContextoRecomendacion $contexto): array
    {
        $despertar = $contexto->minutoArranqueHabitual ?? self::ARRANQUE_POR_DEFECTO;
        $acostarse = $this->menosHoras($despertar, self::HORAS_OBJETIVO);
        $masTarde = $this->menosHoras($despertar, 7);
        $masTemprano = $this->menosHoras($despertar, 9);

        $origen = $contexto->minutoArranqueHabitual === null
            ? 'Todavía no hay bloques registrados, así que se usa 07:00 como hora de despertar.'
            : 'Se calcula con la hora en que solés arrancar el día.';

        $recomendaciones = [
            new Recomendacion(
                titulo: 'Tu horario de sueño sugerido',
                mensaje: $origen.' Se apunta a 8 horas de sueño; el consenso AASM/SRS pide 7 horas o más y no fija un tope. Con un rango de 7 a 9 horas, te acostarías entre las '
                    .$this->formatear($masTemprano).' y las '.$this->formatear($masTarde).'.',
                tipo: TipoRecomendacion::Info,
                icono: 'bi-moon-stars',
                prioridad: $contexto->minutoDelDia() >= 19 * 60 ? 72 : 40,
                fuente: Fuente::aasm(),
                datos: [
                    'Hora sugerida para acostarte' => $this->formatear($acostarse),
                    'Hora sugerida para despertar' => $this->formatear($despertar),
                ],
            ),
        ];

        // Minutos que faltan para la hora de acostarse (negativo: ya pasó).
        $faltan = (($acostarse - $contexto->minutoDelDia() + 720) % 1440 + 1440) % 1440 - 720;

        if ($faltan <= 120 && $faltan >= -120) {
            $recomendaciones[] = new Recomendacion(
                titulo: 'Se acerca la hora de dormir',
                mensaje: ($faltan >= 0 ? 'Faltan menos de 2 horas para tu hora sugerida de acostarte.' : 'Ya pasó tu hora sugerida de acostarte.')
                    .' Cerrá las pantallas y no empieces tareas pesadas. Dormir mal dificulta aprender y consolidar lo estudiado (la sugerencia de las pantallas no tiene fuente; el efecto del sueño en la memoria sí).',
                tipo: TipoRecomendacion::Sugerencia,
                icono: 'bi-phone-vibrate',
                prioridad: 95,
                fuente: Fuente::memoriaYSueno(),
            );
        }

        return $recomendaciones;
    }

    private function menosHoras(int $minuto, int $horas): int
    {
        return (($minuto - $horas * 60) % 1440 + 1440) % 1440;
    }

    private function formatear(int $minuto): string
    {
        return sprintf('%02d:%02d', intdiv($minuto, 60), $minuto % 60);
    }
}
