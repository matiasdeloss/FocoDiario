<?php

namespace App\Services\Calendario;

use App\Enums\ColorNota;
use App\Enums\EstadoTarea;
use App\Enums\PrioridadTarea;
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
        $filas = [];

        if (filled($tarea->proyecto)) {
            $filas[] = ['etiqueta' => 'Proyecto', 'valor' => $tarea->proyecto];
        }

        $estado = $tarea->estado->etiqueta();
        $columna = $tarea->columna?->nombre;
        $filas[] = ['etiqueta' => 'Estado', 'valor' => $columna && $columna !== $estado ? "{$estado} · {$columna}" : $estado];

        return [
            ...$this->tarjetas->datos($tarea),
            'etiqueta_comentario' => 'Descripción',
            'prioridad' => $tarea->prioridad->value,
            'prioridades' => collect(PrioridadTarea::cases())
                ->map(fn (PrioridadTarea $p) => ['valor' => $p->value, 'etiqueta' => $p->etiqueta()])->all(),
            'completada' => $completada,
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
            'filas' => [[
                'etiqueta' => 'Aviso',
                'valor' => $avisado ? 'Ya avisó el '.$avisado->format('d/m/Y').' a las '.$avisado->format('H:i') : 'Todavía no avisó',
            ]],
        ];
    }

    private function nota(Nota $nota): array
    {
        $nota->loadMissing('contexto');

        return [
            ...$this->tarjetas->datos($nota),
            'etiqueta_comentario' => 'Contenido',
            'etiqueta_fecha' => 'Fecha',
            'color' => $nota->color?->value,
            'colores' => collect(ColorNota::cases())->map(fn (ColorNota $c) => [
                'valor' => $c->value, 'etiqueta' => $c->etiqueta(), 'fondo' => $c->fondo(), 'marca' => $c->marca(),
            ])->all(),
            'filas' => $nota->contexto ? [['etiqueta' => 'Materia', 'valor' => $nota->contexto->nombre]] : [],
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
