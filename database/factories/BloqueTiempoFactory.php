<?php

namespace Database\Factories;

use App\Enums\OrigenBloque;
use App\Models\BloqueTiempo;
use App\Models\Categoria;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BloqueTiempo>
 */
class BloqueTiempoFactory extends Factory
{
    public function definition(): array
    {
        $inicio = fake()->dateTimeBetween('-1 week', 'now');

        return [
            'categoria_id' => Categoria::factory(),
            'tarea_id' => null,
            'inicio' => $inicio,
            'fin' => (clone $inicio)->modify('+25 minutes'),
            'origen' => OrigenBloque::Manual,
            'concentracion' => fake()->optional()->numberBetween(1, 5),
        ];
    }
}
