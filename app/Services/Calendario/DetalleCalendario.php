<?php

namespace App\Services\Calendario;

use App\Enums\ColorNota;
use App\Enums\EstadoTarea;
use App\Enums\PrioridadTarea;
use App\Models\Contexto;
use App\Models\Nota;
use App\Models\Recordatorio;
use App\Models\SesionEstudio;
use App\Models\Tarea;
use Illuminate\Database\Eloquent\Model;

/**
 * Detalle completo de una card del calendario para el panel lateral: los campos editables
 * (los mismos de la tarjeta simple) más filas de solo lectura ya formateadas.
 */
class DetalleCalendario
{
    public function __construct(private readonly TarjetasCalendario $tarjetas)
    {
    }

    public function buscar(string $tipo, int $id): Model
    {
        return $tipo === 'sesion'
            ? SesionEstudio::with(['contexto', 'intervalos'])->findOrFail($id)
            : $this->tarjetas->buscar($tipo, $id);
    }

    /** @return array<string, mixed> */
    public function datos(Model $modelo): array
    {
        return match (true) {
            $modelo instanceof Tarea => $this->tarea($modelo),
            $modelo instanceof Recordatorio => $this->recordatorio($modelo),
            $modelo instanceof Nota => $this->nota($modelo),
            $modelo instanceof SesionEstudio => $this->sesion($modelo),
        };
    }

    private function tarea(Tarea $tarea): array
    {
        $tarea->loadMissing('columna');
        $completada = $tarea->estado === EstadoTarea::Completada;
        // Proyecto y estado se editan en el panel: el resumen solo agrega la columna del tablero si tiene otro nombre.
        $filas = [];
        $columna = $tarea->columna?->nombre;

        if ($columna && $columna !== $tarea->estado->etiqueta()) {
            $filas[] = ['etiqueta' => 'Columna', 'valor' => $columna];
        }

        return [
            ...$this->tarjetas->datos($tarea),
            'etiqueta_comentario' => 'Descripción',
            'prioridad' => $tarea->prioridad->value,
            'prioridades' => collect(PrioridadTarea::cases())
                ->map(fn (PrioridadTarea $p) => ['valor' => $p->value, 'etiqueta' => $p->etiqueta()])->all(),
            'completada' => $completada,
            'estado' => $tarea->estado->value,
            'estados' => collect(EstadoTarea::cases())
                ->map(fn (EstadoTarea $e) => ['valor' => $e->value, 'etiqueta' => $e->etiqueta()])->all(),
            'proyecto' => $tarea->proyecto,
            'proyectos' => Tarea::proyectos()->all(),
            'vencida' => $tarea->estaVencida(),
            'etiqueta_fecha' => 'Fecha límite',
            'ayuda_fecha' => $completada ? 'Las tareas completadas no se pueden reubicar.' : null,
            'filas' => $filas,
        ];
    }

    private function recordatorio(Recordatorio $recordatorio): array
    {
        $avisado = $recordatorio->avisado_en;

        return [
            ...$this->tarjetas->datos($recordatorio),
            'etiqueta_comentario' => 'Descripción',
            'etiqueta_fecha' => 'Fecha y hora del aviso',
            'ayuda_fecha' => $avisado ? 'Los recordatorios ya avisados no se pueden reubicar.' : null,
            'avisado' => $avisado !== null,
            // Solo si ya avisó: la acción de volverlo a pendiente.
            'url_reactivar' => $avisado ? route('recordatorios.reactivar', $recordatorio) : null,
            'tarea_id' => $recordatorio->tarea_id,
            // Tareas abiertas, más la ya vinculada aunque esté completada.
            'tareas' => Tarea::abiertas()
                ->when($recordatorio->tarea_id, fn ($consulta, $id) => $consulta->orWhere('id', $id))
                ->orderBy('titulo')->get(['id', 'titulo'])
                ->map(fn (Tarea $t) => ['valor' => $t->id, 'etiqueta' => $t->titulo !== '' ? $t->titulo : 'Sin título'])->all(),
            'filas' => [[
                'etiqueta' => 'Aviso',
                'valor' => $avisado
                    ? 'Ya avisó el '.$avisado->format('d/m/Y').' a las '.$avisado->format('H:i')
                    : 'Todavía no avisó'.($this->avisaraEnSeguida($recordatorio) ? '. Volverá a avisar en el próximo chequeo.' : ''),
            ]],
        ];
    }

    /** Pendiente con la hora ya llegada (últimas 24 h, como lo que revisa el aviso periódico): volverá a avisar solo. */
    private function avisaraEnSeguida(Recordatorio $recordatorio): bool
    {
        return $recordatorio->avisado_en === null
            && $recordatorio->recordar_en !== null
            && $recordatorio->recordar_en->isPast()
            && $recordatorio->recordar_en->greaterThanOrEqualTo(now()->subDay());
    }

    private function nota(Nota $nota): array
    {
        $nota->loadMissing('contexto');

        return [
            ...$this->tarjetas->datos($nota),
            'etiqueta_comentario' => 'Contenido',
            'etiqueta_fecha' => 'Fecha',
            'color' => $nota->color?->value,
            'contexto_id' => $nota->contexto_id,
            'destinos' => Contexto::opciones()->map(fn (string $ruta, int $id) => ['valor' => $id, 'etiqueta' => $ruta])->values()->all(),
            'fijada' => $nota->fijada,
            'colores' => collect(ColorNota::cases())->map(fn (ColorNota $c) => [
                'valor' => $c->value, 'etiqueta' => $c->etiqueta(), 'fondo' => $c->fondo(), 'marca' => $c->marca(),
            ])->all(),
            'filas' => [], // la materia se elige en el panel
        ];
    }

    private function sesion(SesionEstudio $sesion): array
    {
        $sesion->loadMissing(['contexto', 'intervalos']);
        $filas = [];

        if ($sesion->contexto) {
            $filas[] = ['etiqueta' => 'Materia', 'valor' => $sesion->contexto->nombre];
        }

        $filas[] = ['etiqueta' => 'Estilo', 'valor' => $sesion->estilo->etiqueta()];
        $filas[] = ['etiqueta' => 'Foco', 'valor' => intdiv((int) $sesion->foco_seg, 60).' min'];
        $filas[] = ['etiqueta' => 'Pomodoros completados', 'valor' => (string) $sesion->pomodorosCompletados()];
        $filas[] = ['etiqueta' => 'Estado', 'valor' => $sesion->estado->etiqueta()];
        $filas[] = ['etiqueta' => 'Inicio', 'valor' => $sesion->iniciada_en->format('d/m/Y H:i')];

        if ($sesion->finalizada_en) {
            $filas[] = ['etiqueta' => 'Fin', 'valor' => $sesion->finalizada_en->format('d/m/Y H:i')];
        }

        return [
            'tipo' => 'sesion',
            'id' => $sesion->id,
            'titulo' => $sesion->tema ?: 'Sesión de estudio',
            'fecha' => $sesion->iniciada_en->format('Y-m-d\TH:i'),
            'historial' => route('estudio.historial'),
            'solo_lectura' => true,
            'filas' => $filas,
        ];
    }
}
