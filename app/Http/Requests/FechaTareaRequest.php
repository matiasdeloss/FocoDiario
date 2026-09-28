<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FechaTareaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** "fecha" debe venir siempre; null quita la fecha límite. */
    public function rules(): array
    {
        return [
            'fecha' => ['present', 'nullable', 'date_format:Y-m-d'],
        ];
    }

    public function messages(): array
    {
        return [
            'fecha.present' => 'Indicá la fecha, o null para quitarla.',
            'fecha.date_format' => 'La fecha debe tener el formato AAAA-MM-DD.',
        ];
    }
}
