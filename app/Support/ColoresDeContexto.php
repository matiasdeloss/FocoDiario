<?php

namespace App\Support;

use App\Enums\ColorActividad;
use App\Models\Contexto;

/**
 * Color de cada contexto del usuario con herencia: el propio o, si no tiene, el del ancestro más cercano que tenga.
 * Carga todos los contextos una sola vez (una consulta), así que sirve para dibujar listas enteras sin una consulta por fila.
 * Es solo para mostrar: nada se copia a notas.color, tareas.color ni contextos.color.
 */
final class ColoresDeContexto
{
    /** @param  array<int, array{nombre: string, color: ?string, padre: ?int}>  $contextos */
    private function __construct(private readonly array $contextos) {}

    public static function delUsuario(): self
    {
        $filas = [];

        foreach (Contexto::query()->get(['id', 'nombre', 'color', 'contexto_padre_id']) as $contexto) {
            $filas[$contexto->id] = ['nombre' => $contexto->nombre, 'color' => $contexto->color, 'padre' => $contexto->contexto_padre_id];
        }

        return new self($filas);
    }

    /** Color de la paleta del contexto o del ancestro más cercano con color; null si ninguno tiene. */
    public function para(?int $contextoId): ?ColorActividad
    {
        return $this->visible($contextoId)?->color;
    }

    /** Lo mismo con su origen: propio si lo tiene el propio contexto; si no, el nombre del ancestro del que viene. */
    public function visible(?int $contextoId): ?ColorVisible
    {
        $visitados = [];
        $inicio = $contextoId;

        while ($contextoId !== null && isset($this->contextos[$contextoId]) && ! isset($visitados[$contextoId])) {
            $visitados[$contextoId] = true;
            $fila = $this->contextos[$contextoId];
            $color = $fila['color'] === null ? null : ColorActividad::tryFrom(strtolower($fila['color']));

            if ($color !== null) {
                return new ColorVisible($color, $contextoId === $inicio, $fila['nombre']);
            }

            $contextoId = $fila['padre'];
        }

        return null;
    }
}
