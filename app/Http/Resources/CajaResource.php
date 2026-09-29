<?php

namespace App\Http\Resources;

use App\Models\Caja;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Caja */
class CajaResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'fecha' => $this->fecha?->toDateString(),
            'semana' => $this->semana?->toDateString(),
            'zona' => $this->zona?->value,
            'contexto_id' => $this->contexto_id,
            'titulo' => $this->titulo,
            'tipo' => $this->tipo->value,
            'contenido' => $this->contenido,
            'items' => $this->itemsLista(),
            'hora_inicio' => $this->hora_inicio,
            'hora_fin' => $this->hora_fin,
            'x' => $this->x,
            'y' => $this->y,
            'ancho' => $this->ancho,
            'alto' => $this->alto,
            'hecha' => $this->hecha,
        ];
    }
}
