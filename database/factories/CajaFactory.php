<?php

namespace Database\Factories;

use App\Enums\TipoCaja;
use App\Enums\ZonaSemana;
use App\Models\Caja;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Caja>
 */
class CajaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'fecha' => today()->toDateString(),
            'semana' => null,
            'zona' => null,
            'contexto_id' => null,
            'titulo' => fake()->words(2, true),
            'tipo' => TipoCaja::Texto,
            'contenido' => fake()->sentence(),
            'items' => null,
            'hora_inicio' => null,
            'hora_fin' => null,
            'x' => 0,
            'y' => 0,
            'ancho' => 6,
            'alto' => 8,
            'orden' => 0,
            'hecha' => false,
        ];
    }

    public function delDia(Carbon|string $fecha): static
    {
        return $this->state(['fecha' => Carbon::parse($fecha)->toDateString(), 'semana' => null, 'zona' => null]);
    }

    public function deLaSemana(Carbon|string $lunes, ZonaSemana $zona = ZonaSemana::Notas): static
    {
        return $this->state([
            'fecha' => null,
            'semana' => Carbon::parse($lunes)->toDateString(),
            'zona' => $zona,
        ]);
    }

    /** @param  list<array{texto: string, hecho: bool}>|null  $items */
    public function lista(?array $items = null): static
    {
        return $this->state([
            'tipo' => TipoCaja::Lista,
            'contenido' => null,
            'items' => $items ?? [['texto' => 'Leer resumen', 'hecho' => false], ['texto' => 'Repasar clase', 'hecho' => true]],
        ]);
    }

    public function conHora(string $inicio, ?string $fin = null): static
    {
        return $this->state(['hora_inicio' => $inicio, 'hora_fin' => $fin]);
    }

    public function hecha(): static
    {
        return $this->state(['hecha' => true]);
    }
}
