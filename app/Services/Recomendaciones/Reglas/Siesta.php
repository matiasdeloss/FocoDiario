<?php

namespace App\Services\Recomendaciones\Reglas;

use App\Enums\TipoRecomendacion;
use App\Services\Recomendaciones\ContextoRecomendacion;
use App\Services\Recomendaciones\Fuente;
use App\Services\Recomendaciones\Recomendacion;
use App\Services\Recomendaciones\Regla;

/** Nappuccino: evidencia débil (12 personas, simulador de manejo). Se ofrece como opción, no como norma. */
class Siesta implements Regla
{
    public function evaluar(ContextoRecomendacion $contexto): array
    {
        $minuto = $contexto->minutoDelDia();

        if ($minuto < 13 * 60 || $minuto >= 15 * 60 + 30) {
            return [];
        }

        return [new Recomendacion(
            titulo: 'Si te cae el sueño, probá una siesta corta',
            mensaje: 'Un café seguido de una siesta de 10 a 20 minutos es una opción para el bajón de la tarde. La evidencia es débil: pocas personas y en contexto de manejo. No la hagas tarde para no afectar el sueño de la noche.',
            tipo: TipoRecomendacion::Info,
            icono: 'bi-cup-straw',
            prioridad: 30,
            fuente: Fuente::reynerHorne(),
        )];
    }
}
