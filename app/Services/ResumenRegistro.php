<?php

namespace App\Services;

use App\Enums\TipoCategoria;
use App\Models\BloqueTiempo;
use Illuminate\Support\Collection;

class ResumenRegistro
{
    /**
     * Minutos totales y por tipo de categoría. Los bloques deben venir con la categoría cargada.
     *
     * @param  Collection<int, BloqueTiempo>  $bloques
     * @return array{total: int, productiva: int, ocio: int, descanso: int}
     */
    public static function deBloques(Collection $bloques): array
    {
        $resumen = ['total' => 0, 'productiva' => 0, 'ocio' => 0, 'descanso' => 0];

        foreach ($bloques as $bloque) {
            $minutos = $bloque->duracionEnMinutos();
            $resumen['total'] += $minutos;

            $tipo = $bloque->categoria->tipo;
            if ($tipo instanceof TipoCategoria) {
                $resumen[$tipo->value] += $minutos;
            }
        }

        return $resumen;
    }
}
