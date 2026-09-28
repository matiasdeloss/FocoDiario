<?php

namespace Database\Factories;

use App\Enums\TipoCategoria;
use App\Models\Categoria;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Categoria>
 */
class CategoriaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nombre' => fake()->unique()->word(),
            'tipo' => fake()->randomElement(TipoCategoria::cases()),
        ];
    }
}
