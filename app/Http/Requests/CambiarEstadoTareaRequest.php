<?php

namespace App\Http\Requests;

use App\Enums\EstadoTarea;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CambiarEstadoTareaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'estado' => ['required', Rule::enum(EstadoTarea::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'estado.required' => 'Indicá el nuevo estado.',
            'estado.enum' => 'El estado elegido no es válido.',
        ];
    }
}
