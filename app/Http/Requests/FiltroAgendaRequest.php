<?php

namespace App\Http\Requests;

use App\Services\Agenda\Semana;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/** Parámetro ?semana=AAAA-MM-DD del planner: cualquier fecha se normaliza al lunes de su semana. */
class FiltroAgendaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Una fecha mal escrita en la URL no debe romper la página: se ignora y se muestra la semana actual. */
    protected function prepareForValidation(): void
    {
        if (! Semana::esFechaValida($this->query('semana'))) {
            $this->query->remove('semana');
        }
    }

    public function rules(): array
    {
        return [
            'semana' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    public function messages(): array
    {
        return [
            'semana.date_format' => 'La semana debe ser una fecha con el formato AAAA-MM-DD.',
        ];
    }

    public function lunes(): CarbonImmutable
    {
        return Semana::lunesDe($this->query('semana'));
    }
}
