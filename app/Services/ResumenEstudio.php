<?php

namespace App\Services;

use App\Enums\TipoIntervalo;
use Illuminate\Support\Collection;

class ResumenEstudio
{
    /**
     * Totales de un conjunto de intervalos: pomodoros completos e interrumpidos y segundos
     * de foco, de descanso y de tiempo libre.
     *
     * @param  Collection<int, \App\Models\IntervaloEstudio>  $intervalos
     * @return array{pomodoros: int, interrumpidos: int, foco_seg: int, descanso_seg: int, libre_seg: int}
     */
    public static function deIntervalos(Collection $intervalos): array
    {
        $foco = $intervalos->where('tipo', TipoIntervalo::Foco);

        return [
            'pomodoros' => $foco->where('completado', true)->count(),
            'interrumpidos' => $foco->where('completado', false)->count(),
            'foco_seg' => (int) $foco->sum('duracion_seg'),
            'descanso_seg' => (int) $intervalos->where('tipo', TipoIntervalo::Descanso)->sum('duracion_seg'),
            'libre_seg' => (int) $intervalos->where('tipo', TipoIntervalo::Libre)->sum('duracion_seg'),
        ];
    }
}
