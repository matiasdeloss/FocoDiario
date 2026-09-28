<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FechaRecordatorioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'recordar_en' => ['required', 'date_format:Y-m-d\TH:i:s'],
        ];
    }

    public function messages(): array
    {
        return [
            'recordar_en.required' => 'Indicá la nueva fecha y hora del recordatorio.',
            'recordar_en.date_format' => 'La fecha y hora del recordatorio no son válidas.',
        ];
    }
}
