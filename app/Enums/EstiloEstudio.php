<?php

namespace App\Enums;

enum EstiloEstudio: string
{
    case Clasico = 'clasico';
    case BloquesLargos = 'bloques_largos';
    case Personalizado = 'personalizado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Clasico => 'Pomodoro clásico',
            self::BloquesLargos => 'Bloques largos',
            self::Personalizado => 'Personalizado',
        };
    }

    /** Tiempos del preset: foco, descanso corto, descanso largo y pomodoros antes del largo. */
    public function tiempos(): ?array
    {
        return match ($this) {
            self::Clasico => ['foco' => 25, 'descanso' => 5, 'largo' => 15, 'ciclos' => 4],
            self::BloquesLargos => ['foco' => 50, 'descanso' => 10, 'largo' => 20, 'ciclos' => 3],
            self::Personalizado => null,
        };
    }

    /** Presets para el temporizador: [clave => tiempos]. */
    public static function presets(): array
    {
        $presets = [];

        foreach (self::cases() as $estilo) {
            if ($estilo->tiempos() !== null) {
                $presets[$estilo->value] = $estilo->tiempos();
            }
        }

        return $presets;
    }
}
