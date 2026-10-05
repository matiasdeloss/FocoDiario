<?php

namespace Database\Factories;

use App\Enums\TipoContexto;
use App\Models\Contexto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contexto>
 */
class ContextoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nombre' => fake()->unique()->words(2, true),
            'tipo' => TipoContexto::Materia,
            'contexto_padre_id' => null,
            'color' => null,
        ];
    }

    public function entorno(): static
    {
        return $this->state(['tipo' => TipoContexto::Entorno]);
    }

    public function proyecto(): static
    {
        return $this->state(['tipo' => TipoContexto::Proyecto]);
    }

    public function tema(): static
    {
        return $this->state(['tipo' => TipoContexto::Tema]);
    }
}
