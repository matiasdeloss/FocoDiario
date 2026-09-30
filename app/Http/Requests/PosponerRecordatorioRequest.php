<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PosponerRecordatorioRequest extends FormRequest
{
    /** Minutos que se pospone si no se indica otra cosa ("10 min más" del toast). */
    public const MINUTOS_POR_DEFECTO = 10;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'minutos' => ['nullable', 'integer', 'between:1,1440'],
        ];
    }

    public function messages(): array
    {
        return [
            'minutos.integer' => 'Los minutos tienen que ser un número entero.',
            'minutos.between' => 'Se puede posponer entre 1 minuto y 24 horas.',
        ];
    }

    public function minutos(): int
    {
        return (int) ($this->validated('minutos') ?? self::MINUTOS_POR_DEFECTO);
    }
}
