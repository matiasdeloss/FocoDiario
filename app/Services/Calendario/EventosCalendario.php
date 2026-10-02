<?php

namespace App\Services\Calendario;

use App\Enums\EstadoTarea;
use App\Models\Caja;
use App\Models\Nota;
use App\Models\Recordatorio;
use App\Models\SesionEstudio;
use App\Models\Tarea;
use Illuminate\Support\Carbon;

/**
 * Arma los eventos del calendario (formato de FullCalendar) y los conteos
 * por día de la tira semanal. Una consulta por tipo, sin N+1.
 * El rango es [desde, hasta): el fin no se incluye.
 */
class EventosCalendario
{
    public const TIPOS = ['tarea', 'recordatorio', 'nota', 'sesion', 'planner'];

    public function __construct(private readonly TarjetasCalendario $tarjetas)
    {
    }

    /**
     * @param  list<string>  $tipos  capas a incluir
     * @return list<array<string, mixed>>
     */
    public function eventos(Carbon $desde, Carbon $hasta, array $tipos = self::TIPOS): array
    {
        $eventos = [];

        if (in_array('tarea', $tipos, true)) {
            $tareas = Tarea::query()
                ->where('fecha_limite', '>=', $desde->toDateString())
                ->where('fecha_limite', '<', $hasta->toDateString())
                ->orderBy('id')
                ->get();

            foreach ($tareas as $tarea) {
                $eventos[] = $this->eventoTarea($tarea);
            }
        }

        if (in_array('recordatorio', $tipos, true)) {
            $recordatorios = Recordatorio::query()
                ->where('recordar_en', '>=', $desde)
                ->where('recordar_en', '<', $hasta)
                ->orderBy('recordar_en')
                ->get();

            foreach ($recordatorios as $recordatorio) {
                $eventos[] = $this->eventoRecordatorio($recordatorio);
            }
        }

        if (in_array('nota', $tipos, true)) {
            $notas = Nota::query()
                ->where('fecha', '>=', $desde->toDateString())
                ->where('fecha', '<', $hasta->toDateString())
                ->orderBy('id')
                ->get();

            foreach ($notas as $nota) {
                $eventos[] = $this->eventoNota($nota);
            }
        }

        if (in_array('sesion', $tipos, true)) {
            $sesiones = SesionEstudio::query()
                ->where('iniciada_en', '>=', $desde)
                ->where('iniciada_en', '<', $hasta)
                ->orderBy('iniciada_en')
                ->get();

            foreach ($sesiones as $sesion) {
                $evento = [
                    'id' => 'sesion-'.$sesion->id,
                    'title' => $sesion->tema ?: 'Sesión de estudio',
                    'start' => $sesion->iniciada_en->format('Y-m-d\TH:i:s'),
                    'allDay' => false,
                    'url' => route('estudio.historial'),
                    'editable' => false,
                    'classNames' => ['ev-tipo-sesion'],
                    'extendedProps' => [
                        'tipo' => 'sesion',
                        'sesionId' => $sesion->id,
                        'estado' => $sesion->estado->etiqueta(),
                        'foco_seg' => $sesion->foco_seg,
                    ],
                ];

                if ($sesion->finalizada_en) {
                    $evento['end'] = $sesion->finalizada_en->format('Y-m-d\TH:i:s');
                }

                $eventos[] = $evento;
            }
        }

        if (in_array('planner', $tipos, true)) {
            // Solo cajas de un día (las de zona semanal no tienen fecha y quedan fuera).
            $cajas = Caja::query()
                ->with('actividad')
                ->entreFechas($desde, $hasta->copy()->subDay())
                ->orderBy('fecha')
                ->enOrdenDePlanner()
                ->get();

            foreach ($cajas as $caja) {
                $eventos[] = $this->eventoPlanner($caja);
            }
        }

        return $eventos;
    }

    /** Evento de una caja del planner: con hora si la tiene (fin = hora_fin o inicio + 1 h), si no de todo el día. */
    public function eventoPlanner(Caja $caja): array
    {
        $dia = $caja->fecha->toDateString();
        $conHora = $caja->hora_inicio !== null;
        $color = $caja->actividad?->colorActividad();

        $evento = [
            'id' => 'planner-'.$caja->id,
            'title' => $caja->tituloVisible(),
            'start' => $conHora ? $dia.'T'.$caja->hora_inicio.':00' : $dia,
            'allDay' => ! $conHora,
            'editable' => true,
            'durationEditable' => $conHora,
            'classNames' => array_values(array_filter(['ev-tipo-planner', $caja->hecha ? 'ev-hecho' : null, $color?->clase()])),
            'extendedProps' => [
                'tipo' => 'planner',
                'plannerId' => $caja->id,
                'tieneFin' => $conHora && $caja->hora_fin !== null,
                'actividad' => $caja->actividad?->nombre,
                'urlDia' => route('agenda.dia', ['fecha' => $dia]),
            ],
        ];

        if ($conHora) {
            $evento['end'] = $caja->hora_fin !== null
                ? $dia.'T'.$caja->hora_fin.':00'
                : Carbon::parse($dia.' '.$caja->hora_inicio)->addHour()->format('Y-m-d\TH:i:s');
        }

        return $evento;
    }

    /** Evento de un recordatorio con fecha (con hora). Editable mientras no esté avisado. */
    public function eventoRecordatorio(Recordatorio $recordatorio): array
    {
        return [
            'id' => 'recordatorio-'.$recordatorio->id,
            'title' => $recordatorio->mensaje !== '' ? $recordatorio->mensaje : 'Sin título',
            'start' => $recordatorio->recordar_en->format('Y-m-d\TH:i:s'),
            'allDay' => false,
            'editable' => $recordatorio->avisado_en === null,
            'classNames' => array_values(array_filter([
                'ev-tipo-recordatorio',
                $recordatorio->avisado_en ? 'ev-hecho' : null,
            ])),
            'extendedProps' => [
                'tipo' => 'recordatorio',
                'recordatorioId' => $recordatorio->id,
                'tareaId' => $recordatorio->tarea_id,
                'avisado' => $recordatorio->avisado_en !== null,
                'tarjeta' => $this->tarjetas->datos($recordatorio),
            ],
        ];
    }

    /** Evento de una nota con fecha (todo el día). */
    public function eventoNota(Nota $nota): array
    {
        $titulo = $nota->tituloVisible();

        return [
            'id' => 'nota-'.$nota->id,
            'title' => $titulo !== '' ? $titulo : 'Sin título',
            'start' => $nota->fecha->toDateString(),
            'allDay' => true,
            'editable' => true,
            'classNames' => ['ev-tipo-nota'],
            'extendedProps' => [
                'tipo' => 'nota',
                'notaId' => $nota->id,
                'fijada' => $nota->fijada,
                'tarjeta' => $this->tarjetas->datos($nota),
            ],
        ];
    }

    /** Evento de una tarea con fecha límite (todo el día). */
    public function eventoTarea(Tarea $tarea): array
    {
        $completada = $tarea->estado === EstadoTarea::Completada;
        $vencida = $tarea->estaVencida();

        return [
            'id' => 'tarea-'.$tarea->id,
            'title' => $tarea->titulo !== '' ? $tarea->titulo : 'Sin título',
            'start' => $tarea->fecha_limite->toDateString(),
            'allDay' => true,
            'editable' => ! $completada,
            'classNames' => array_values(array_filter([
                'ev-tipo-tarea',
                'ev-prio-'.$tarea->prioridad->value,
                $completada ? 'ev-hecho' : null,
                $vencida ? 'ev-vencida' : null,
            ])),
            'extendedProps' => [
                'tipo' => 'tarea',
                'tareaId' => $tarea->id,
                'prioridad' => $tarea->prioridad->value,
                'prioridadEtiqueta' => $tarea->prioridad->etiqueta(),
                'estado' => $tarea->estado->value,
                'estadoEtiqueta' => $tarea->estado->etiqueta(),
                'proyecto' => $tarea->proyecto,
                'vencida' => $vencida,
                'completada' => $completada,
                'tarjeta' => $this->tarjetas->datos($tarea),
            ],
        ];
    }

    /**
     * Conteos por día y tipo para la tira semanal.
     *
     * @return array<string, array<string, int>> p. ej. ['2026-09-28' => ['tarea' => 2]]
     */
    public function conteosPorDia(Carbon $desde, Carbon $hasta): array
    {
        $conteos = [];

        $agregar = function (string $tipo, $filas) use (&$conteos) {
            foreach ($filas as $fila) {
                $conteos[Carbon::parse($fila->dia)->toDateString()][$tipo] = (int) $fila->total;
            }
        };

        $agregar('tarea', Tarea::query()
            ->selectRaw('fecha_limite as dia, count(*) as total')
            ->where('fecha_limite', '>=', $desde->toDateString())
            ->where('fecha_limite', '<', $hasta->toDateString())
            ->groupBy('fecha_limite')
            ->get());

        $agregar('recordatorio', Recordatorio::query()
            ->selectRaw('date(recordar_en) as dia, count(*) as total')
            ->where('recordar_en', '>=', $desde)
            ->where('recordar_en', '<', $hasta)
            ->groupByRaw('date(recordar_en)')
            ->get());

        $agregar('nota', Nota::query()
            ->selectRaw('fecha as dia, count(*) as total')
            ->where('fecha', '>=', $desde->toDateString())
            ->where('fecha', '<', $hasta->toDateString())
            ->groupBy('fecha')
            ->get());

        $agregar('sesion', SesionEstudio::query()
            ->selectRaw('date(iniciada_en) as dia, count(*) as total')
            ->where('iniciada_en', '>=', $desde)
            ->where('iniciada_en', '<', $hasta)
            ->groupByRaw('date(iniciada_en)')
            ->get());

        return $conteos;
    }

    /**
     * Los 7 días de la semana actual (lunes a domingo) con sus conteos.
     *
     * @return list<array{fecha: Carbon, hoy: bool, conteos: array<string, int>}>
     */
    public function semanaActual(): array
    {
        $lunes = today()->startOfWeek(Carbon::MONDAY);
        $conteos = $this->conteosPorDia($lunes, $lunes->copy()->addWeek());

        return collect(range(0, 6))->map(function (int $i) use ($lunes, $conteos) {
            $dia = $lunes->copy()->addDays($i);

            return [
                'fecha' => $dia,
                'hoy' => $dia->isToday(),
                'conteos' => $conteos[$dia->toDateString()] ?? [],
            ];
        })->all();
    }
}
