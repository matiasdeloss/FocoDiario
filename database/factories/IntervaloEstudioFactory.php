<?php

namespace Database\Factories;

use App\Enums\TipoIntervalo;
use App\Models\IntervaloEstudio;
use App\Models\SesionEstudio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IntervaloEstudio>
 */
class IntervaloEstudioFactory extends Factory
{
    public function definition(): array
    {
        $inicio = fake()->dateTimeBetween('-1 week', 'now');

        return [
            'sesion_id' => SesionEstudio::factory(),
            'tipo' => TipoIntervalo::Foco,
            'clave' => fake()->unique()->uuid(),
            'inicio' => $inicio,
            'fin' => (clone $inicio)->modify('+25 minutes'),
            'planificado_seg' => 1500,
            'pausado_seg' => 0,
            'duracion_seg' => 1500,
            'completado' => true,
            'bloque_tiempo_id' => null,
        ];
    }
}
