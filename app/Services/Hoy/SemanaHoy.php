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

    private const COMPLETOS = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];

    private const CORTOS = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];

    /** Máximo de puntitos por casillero; el resto se resume en "+N". */
    public const MAX_PUNTOS = 5;

    private const ETIQUETAS_TIPO = [
        'tarea' => ['tarea', 'tareas'],
        'recordatorio' => ['recordatorio', 'recordatorios'],
        'nota' => ['nota', 'notas'],
        'sesion' => ['sesión de estudio', 'sesiones de estudio'],
    ];

    private const ETIQUETAS_SIN_HORA = ['tarea' => 'Fecha límite', 'nota' => 'Nota'];

    public function __construct(private readonly EventosCalendario $calendario)
    {
    }

    /**
     * @return list<array{fecha: string, nombre: string, corto: string, completo: string, numero: int, largo: string, hoy: bool, eventos: list<array<string, mixed>>}>
     */
    public function datos(): array
    {
        $lunes = today()->startOfWeek(Carbon::MONDAY);

        $porDia = collect($this->calendario->eventos($lunes, $lunes->copy()->addWeek()))
            ->map(fn (array $evento) => $this->resumir($evento))
            ->groupBy('fecha');

        return collect(range(0, 6))->map(function (int $i) use ($lunes, $porDia) {
            $dia = $lunes->copy()->addDays($i);
            $eventos = $this->ordenar($porDia->get($dia->toDateString(), collect()));

            return [
                'fecha' => $dia->toDateString(),
                'nombre' => self::NOMBRES[$i],
                'corto' => self::CORTOS[$i],
                'completo' => self::COMPLETOS[$i],
                'numero' => $dia->day,
                'largo' => $dia->translatedFormat('l j'),
                'hoy' => $dia->isToday(),
                'eventos' => $eventos,
                'puntos' => $this->puntos($eventos),
                'resumen' => $this->resumen($eventos),
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

    /**
     * Puntitos del casillero: orden estable por tipo (tarea, recordatorio, nota, sesión) y, dentro
     * de cada tipo, por hora. Trae todos; la vista muestra hasta MAX_PUNTOS.
     *
     * @param  list<array<string, mixed>>  $eventos
     * @return list<array{tipo: string, hecho: bool}>
     */
    private function puntos(array $eventos): array
    {
        $orden = array_flip(EventosCalendario::TIPOS);

        return collect($eventos)
            ->sortBy(fn (array $e) => $orden[$e['tipo']] ?? 99) // sortBy es estable: conserva el orden por hora
            ->map(fn (array $e) => ['tipo' => $e['tipo'], 'hecho' => $e['hecho']])
            ->values()
            ->all();
    }

    /** Resumen hablado por tipo, p. ej. "2 tareas, 1 recordatorio". Vacío si no hay eventos. */
    private function resumen(array $eventos): string
    {
        $conteos = collect($eventos)->countBy('tipo');

        return collect(EventosCalendario::TIPOS)
            ->filter(fn (string $tipo) => $conteos->has($tipo))
            ->map(function (string $tipo) use ($conteos) {
                $n = $conteos[$tipo];

                return $n.' '.self::ETIQUETAS_TIPO[$tipo][$n === 1 ? 0 : 1];
            })
            ->implode(', ');
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
