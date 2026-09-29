<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Título editable del planner semanal. Vacío vuelve al título por defecto. */
class TituloPlannerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['titulo' => ['present', 'nullable', 'string', 'max:60']];
    }

    public function messages(): array
    {
        return ['titulo.max' => 'El título puede tener hasta 60 caracteres.'];
    }

    public function titulo(): string
    {
        return trim((string) $this->validated('titulo'));
    }
}
