<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecordatorioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mensaje' => ['required', 'string', 'max:255'],
            'recordar_en' => ['required', 'date'],
            'tarea_id' => ['nullable', 'integer', 'exists:tareas,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'mensaje.required' => 'Escribí el mensaje del recordatorio.',
            'mensaje.max' => 'El mensaje no puede superar los 255 caracteres.',
            'recordar_en.required' => 'Indicá cuándo querés que te lo recordemos.',
            'recordar_en.date' => 'La fecha y hora del recordatorio no son válidas.',
            'tarea_id.exists' => 'La tarea elegida ya no existe.',
            'tarea_id.integer' => 'La tarea elegida no es válida.',
        ];
    }
}
