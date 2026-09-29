<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Corre una columna un lugar a la izquierda o a la derecha. */
class MoverColumnaTableroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'direccion' => ['required', 'in:izquierda,derecha'],
        ];
    }

    public function messages(): array
    {
        return [
            'direccion.required' => 'Indicá hacia dónde mover la columna.',
            'direccion.in' => 'La dirección no es válida.',
        ];
    }
}
