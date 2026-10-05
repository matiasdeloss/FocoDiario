<?php

namespace App\Models\Concerns;

use App\Support\ColoresDeContexto;
use App\Support\ColorVisible;

/**
 * Color que se ve en una nota o tarea (requiere `color` con ColorActividad y `contexto_id`): gana el propio;
 * si no tiene, el de su contexto o el del ancestro más cercano con color; si no, ninguno.
 * Al listar, pasar un mismo ColoresDeContexto para no consultar los contextos por cada fila.
 */
trait HeredaColorDeContexto
{
    public function colorVisible(?ColoresDeContexto $colores = null): ?ColorVisible
    {
        if ($this->color !== null) {
            return new ColorVisible($this->color, true);
        }

        if ($this->contexto_id === null) {
            return null;
        }

        $heredado = ($colores ?? ColoresDeContexto::delUsuario())->visible($this->contexto_id);

        // Para la nota o tarea el color nunca es propio, aunque el contexto lo tenga: viene de ese contexto o de un ancestro.
        return $heredado === null ? null : new ColorVisible($heredado->color, false, $heredado->origen);
    }
}
