<?php

namespace Database\Factories;

use App\Models\Nota;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Nota>
 */
class NotaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'contenido' => fake()->sentence(8),
            'contexto_id' => null,
            'fecha' => null,
            'fijada' => false,
        ];
    }

    public function fijada(): static
    {
        return $this->state(['fijada' => true]);
    }
}
