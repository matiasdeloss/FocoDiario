<?php

namespace App\Services\Hoy;

use App\Services\Calendario\EventosCalendario;
use Illuminate\Support\Carbon;

/**
 * Los siete días de la semana actual (lunes a domingo) con sus eventos, listos para embeber en la página.
 * Reutiliza EventosCalendario: una consulta por tipo para toda la semana, sin N+1.
 */
class SemanaHoy
{
    private const NOMBRES = ['Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sa', 'Do'];

    private const ETIQUETAS_SIN_HORA = ['tarea' => 'Fecha límite', 'nota' => 'Nota'];

    public function __construct(private readonly EventosCalendario $calendario)
    {
    }

    /**
     * @return list<array{fecha: string, nombre: string, numero: int, largo: string, hoy: bool, eventos: list<array<string, mixed>>}>
     */
    public function datos(): array
    {
        $lunes = today()->startOfWeek(Carbon::MONDAY);

        $porDia = collect($this->calendario->eventos($lunes, $lunes->copy()->addWeek()))
            ->map(fn (array $evento) => $this->resumir($evento))
            ->groupBy('fecha');

        return collect(range(0, 6))->map(function (int $i) use ($lunes, $porDia) {
            $dia = $lunes->copy()->addDays($i);

            return [
                'fecha' => $dia->toDateString(),
                'nombre' => self::NOMBRES[$i],
                'numero' => $dia->day,
                'largo' => $dia->translatedFormat('l j'),
                'hoy' => $dia->isToday(),
                'eventos' => $this->ordenar($porDia->get($dia->toDateString(), collect())),
            ];
        })->all();
    }

    /** @return array<string, mixed> */
    private function resumir(array $evento): array
    {
        $tipo = $evento['extendedProps']['tipo'];
        $conHora = ! $evento['allDay'];

        return [
            'fecha' => substr($evento['start'], 0, 10),
            'tipo' => $tipo,
            'titulo' => $evento['title'],
            'hora' => $conHora ? substr($evento['start'], 11, 5) : null,
            'detalle' => $conHora ? null : (self::ETIQUETAS_SIN_HORA[$tipo] ?? null),
            'hecho' => in_array('ev-hecho', $evento['classNames'], true),
        ];
    }

    /** Primero los que tienen hora (por hora), después los de todo el día. */
    private function ordenar($eventos): array
    {
        return $eventos
            ->sortBy(fn (array $e) => ($e['hora'] ?? '99:99'))
            ->values()
            ->all();
    }
}
