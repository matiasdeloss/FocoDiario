<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DiaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fecha' => ['required', 'date_format:Y-m-d'],
            'desperto_a' => ['nullable', 'date_format:H:i'],
            'durmio_a' => ['nullable', 'date_format:H:i'],
        ];
    }

    public function messages(): array
    {
        return [
            'fecha.required' => 'Indicá el día.',
            'fecha.date_format' => 'El día no es válido.',
            'desperto_a.date_format' => 'La hora de despertar no es válida.',
            'durmio_a.date_format' => 'La hora de dormir no es válida.',
        ];
    }
}
