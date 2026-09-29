<?php

namespace App\Http\Requests\Concerns;

use App\Enums\TipoCaja;
use Illuminate\Validation\Rule;

/** Reglas y mensajes del contenido de una caja (texto o lista), compartidos entre cajas de un día y de la semana. */
trait ReglasDeContenidoDeCaja
{
    /** @return array<string, mixed> */
    protected function reglasDeContenido(bool $obligatorioTipo): array
    {
        return [
            'tipo' => [$obligatorioTipo ? 'required' : 'sometimes', Rule::enum(TipoCaja::class)],
            'contenido' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'items' => ['sometimes', 'nullable', 'array', 'max:200'],
            'items.*.texto' => ['nullable', 'string', 'max:500'],
            'items.*.hecho' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    protected function mensajesDeContenido(): array
    {
        return [
            'tipo.required' => 'Elegí si la caja es de texto o una lista.',
            'tipo.enum' => 'El tipo de caja elegido no es válido.',
            'contenido.max' => 'El texto no puede superar los 20.000 caracteres.',
            'items.array' => 'La lista no tiene un formato válido.',
            'items.max' => 'La lista no puede tener más de 200 ítems.',
            'items.*.texto.max' => 'Cada ítem puede tener hasta 500 caracteres.',
            'items.*.hecho.boolean' => 'La casilla de un ítem no es válida.',
        ];
    }

    /**
     * Ítems listos para guardar: [{texto, hecho}] sin los renglones completamente vacíos que ya estaban tildados.
     *
     * @param  array<int, array<string, mixed>>|null  $items
     * @return list<array{texto: string, hecho: bool}>|null
     */
    protected function itemsNormalizados(?array $items): ?array
    {
        if ($items === null) {
            return null;
        }

        return array_values(array_map(
            fn ($item) => ['texto' => (string) ($item['texto'] ?? ''), 'hecho' => (bool) ($item['hecho'] ?? false)],
            $items,
        ));
    }
}
