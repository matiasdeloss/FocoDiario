<?php

namespace Database\Factories;

use App\Enums\EstadoTarea;
use App\Enums\PrioridadTarea;
use App\Models\Tarea;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tarea>
 */
class TareaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'titulo' => fake()->sentence(4),
            'proyecto' => fake()->optional()->word(),
            'fecha_limite' => fake()->optional()->dateTimeBetween('now', '+1 month'),
            'prioridad' => fake()->randomElement(PrioridadTarea::cases()),
            'estado' => EstadoTarea::Pendiente,
        ];
    }
}
