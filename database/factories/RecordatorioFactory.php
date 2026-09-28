<?php

namespace Database\Factories;

use App\Models\Recordatorio;
use App\Models\Tarea;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Recordatorio>
 */
class RecordatorioFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tarea_id' => Tarea::factory(),
            'mensaje' => fake()->sentence(),
            'recordar_en' => fake()->dateTimeBetween('now', '+1 week'),
            'avisado_en' => null,
        ];
    }
}
