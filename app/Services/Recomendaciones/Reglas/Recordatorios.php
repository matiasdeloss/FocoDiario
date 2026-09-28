<?php

namespace App\Services\Recomendaciones\Reglas;

use App\Enums\TipoRecomendacion;
use App\Services\Recomendaciones\ContextoRecomendacion;
use App\Services\Recomendaciones\Recomendacion;
use App\Services\Recomendaciones\Regla;

class Recordatorios implements Regla
{
    public function evaluar(ContextoRecomendacion $contexto): array
    {
        $recomendaciones = [];

        if ($contexto->recordatoriosAtrasados->isNotEmpty()) {
            $recomendaciones[] = new Recomendacion(
                titulo: 'Recordatorios atrasados',
                mensaje: 'Tenés '.$contexto->recordatoriosAtrasados->count().' sin marcar como avisados, por ejemplo: "'
                    .$contexto->recordatoriosAtrasados->first()->mensaje.'".',
                tipo: TipoRecomendacion::Alerta,
                icono: 'bi-bell',
                prioridad: 88,
            );
        }

        if ($contexto->recordatoriosProximos->isNotEmpty()) {
            $proximo = $contexto->recordatoriosProximos->first();
            $recomendaciones[] = new Recomendacion(
                titulo: 'Recordatorio en la próxima hora',
                mensaje: '"'.$proximo->mensaje.'" a las '.$proximo->recordar_en->format('H:i').'.',
                tipo: TipoRecomendacion::Info,
                icono: 'bi-alarm',
                prioridad: 80,
            );
        }

        return $recomendaciones;
    }
}
