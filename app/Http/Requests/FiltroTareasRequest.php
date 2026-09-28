<?php

namespace App\Http\Requests;

use App\Enums\EstadoTarea;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FiltroTareasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'estado' => ['nullable', Rule::enum(EstadoTarea::class)],
            'proyecto' => ['nullable', 'string', 'max:255'],
            'vista' => ['nullable', 'in:lista,tablero'],
        ];
    }

    public function messages(): array
    {
        return [
            'estado.enum' => 'El estado del filtro no es válido.',
            'vista.in' => 'La vista elegida no es válida.',
            'proyecto.max' => 'El proyecto del filtro es demasiado largo.',
        ];
    }
}
