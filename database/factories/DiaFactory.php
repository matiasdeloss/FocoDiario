<?php

namespace Database\Factories;

use App\Models\Dia;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Dia>
 */
class DiaFactory extends Factory
{
    public function definition(): array
    {
        $fecha = fake()->unique()->dateTimeBetween('-60 days', 'now');

        return [
            'fecha' => $fecha,
            'desperto_a' => (clone $fecha)->setTime(7, 30),
            'durmio_a' => (clone $fecha)->setTime(23, 30),
        ];
    }
}
