<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Pasa una tarea a otra columna del tablero (arrastrar y soltar o botones). */
class MoverTareaColumnaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'columna_id' => ['required', 'integer', 'exists:columnas_tablero,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'columna_id.required' => 'Indicá la columna de destino.',
            'columna_id.exists' => 'La columna elegida no existe.',
        ];
    }
}
