<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class FechaRecordatorioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * "recordar_en" (fecha y hora) o "fecha" (solo el día, queda a las 09:00).
     * recordar_en en null quita la fecha y devuelve el recordatorio al panel.
     */
    public function rules(): array
    {
        return [
            'recordar_en' => ['nullable', 'date_format:Y-m-d\TH:i:s,Y-m-d\TH:i'],
            'fecha' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    public function messages(): array
    {
        return [
            'recordar_en.date_format' => 'La fecha y hora del recordatorio no son válidas.',
            'fecha.date_format' => 'La fecha debe tener el formato AAAA-MM-DD.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validador) {
                if (! $this->has('recordar_en') && ! $this->has('fecha')) {
                    $validador->errors()->add('recordar_en', 'Indicá la nueva fecha y hora del recordatorio, o null para quitarla.');
                }
            },
        ];
    }

    /** Fecha y hora resultante, o null si se quita. */
    public function momento(): ?string
    {
        return $this->input('recordar_en') ?? ($this->filled('fecha') ? $this->input('fecha') : null);
    }
}
