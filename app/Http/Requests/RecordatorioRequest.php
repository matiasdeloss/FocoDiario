<?php

namespace App\Http\Requests;

use App\Support\ReglasDeUsuario;
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
            'descripcion' => ['nullable', 'string', 'max:5000'],
            // Lo que manda un datetime-local (con o sin segundos) o la misma fecha con espacio.
            'recordar_en' => ['nullable', 'date_format:Y-m-d\TH:i,Y-m-d\TH:i:s,Y-m-d H:i,Y-m-d H:i:s'],
            'tarea_id' => ['nullable', 'integer', ReglasDeUsuario::existe('tareas')],
        ];
    }

    public function messages(): array
    {
        return [
            'mensaje.required' => 'Escribí el mensaje del recordatorio.',
            'mensaje.max' => 'El mensaje no puede superar los 255 caracteres.',
            'descripcion.max' => 'El comentario no puede superar los 5000 caracteres.',
            'recordar_en.date_format' => 'La fecha y hora del recordatorio no son válidas.',
            'tarea_id.exists' => 'La tarea elegida ya no existe.',
            'tarea_id.integer' => 'La tarea elegida no es válida.',
        ];
    }
}
