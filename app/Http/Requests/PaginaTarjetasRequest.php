<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PaginaTarjetasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'excluir' => ['nullable', 'array', 'max:500'],
            'excluir.*' => ['integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'excluir.array' => 'La lista de tarjetas ya mostradas no es válida.',
            'excluir.max' => 'Hay demasiadas tarjetas mostradas.',
            'excluir.*.integer' => 'Una de las tarjetas indicadas no es válida.',
        ];
    }
}
