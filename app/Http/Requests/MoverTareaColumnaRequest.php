<?php

namespace App\Http\Requests;

use App\Support\ReglasDeUsuario;
use Illuminate\Foundation\Http\FormRequest;

/** Pasa una tarea o una nota a otra columna del tablero (arrastrar y soltar o botones), con el orden de la columna de destino. */
class MoverTareaColumnaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'columna_id' => ['required', 'integer', ReglasDeUsuario::existe('columnas_tablero')],
            // Ids de las tarjetas de la columna, de arriba abajo, tal como quedaron al soltar.
            'orden' => ['nullable', 'array', 'max:500'],
            'orden.*' => ['integer', 'distinct'],
            // Con notas y tareas mezcladas en la columna, el orden viaja como "tarea:12" o "nota:5".
            'tarjetas' => ['nullable', 'array', 'max:500'],
            'tarjetas.*' => ['string', 'regex:/^(tarea|nota):\d+$/', 'distinct'],
        ];
    }

    public function messages(): array
    {
        return [
            'columna_id.required' => 'Indicá la columna de destino.',
            'columna_id.exists' => 'La columna elegida no existe.',
            'orden.array' => 'El orden de las tarjetas no es válido.',
            'orden.*.integer' => 'El orden de las tarjetas no es válido.',
            'orden.*.distinct' => 'El orden de las tarjetas no es válido.',
            'tarjetas.array' => 'El orden de las tarjetas no es válido.',
            'tarjetas.*.regex' => 'El orden de las tarjetas no es válido.',
            'tarjetas.*.distinct' => 'El orden de las tarjetas no es válido.',
        ];
    }
}
