<?php

namespace Database\Factories;

use App\Enums\EstadoSesion;
use App\Enums\EstiloEstudio;
use App\Models\SesionEstudio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SesionEstudio>
 */
class SesionEstudioFactory extends Factory
{
    public function definition(): array
    {
        $inicio = fake()->dateTimeBetween('-1 week', 'now');

        return [
            'contexto_id' => null,
            'tarea_id' => null,
            'tema' => fake()->optional()->sentence(3),
            'estilo' => EstiloEstudio::Clasico,
            'foco_seg' => 1500,
            'descanso_seg' => 300,
            'descanso_largo_seg' => 900,
            'pomodoros_antes_largo' => 4,
            'estado' => EstadoSesion::Finalizada,
            'iniciada_en' => $inicio,
            'finalizada_en' => (clone $inicio)->modify('+1 hour'),
        ];
    }

    public function enCurso(): static
    {
        return $this->state(['estado' => EstadoSesion::EnCurso, 'finalizada_en' => null]);
    }
}
