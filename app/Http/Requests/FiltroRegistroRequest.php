<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

class FiltroRegistroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fecha' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    public function messages(): array
    {
        return [
            'fecha.date_format' => 'La fecha elegida no es válida.',
        ];
    }

    /** Día elegido, o hoy si no se indicó. */
    public function dia(): CarbonImmutable
    {
        $fecha = $this->validated('fecha');

        return $fecha ? CarbonImmutable::createFromFormat('Y-m-d', $fecha)->startOfDay() : CarbonImmutable::today();
    }
}
