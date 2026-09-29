<?php

namespace App\Services\Tareas;

use App\Models\Recordatorio;
use App\Models\Tarea;
use Illuminate\Support\Carbon;

/** Una fila de la lista unificada de Tareas: una tarea o un recordatorio, con lo justo para dibujarla y agruparla. */
final class ItemLista
{
    public function __construct(
        public readonly string $tipo,
        public readonly Tarea|Recordatorio $modelo,
        public readonly string $titulo,
        public readonly bool $hecha,
        public readonly ?Carbon $cuando,
        public readonly bool $conHora,
        public readonly ?string $proyecto,
        public readonly ?string $prioridad,
        public readonly ?string $detalle,
    ) {}

    public static function deTarea(Tarea $tarea): self
    {
        return new self(
            'tarea',
            $tarea,
            $tarea->titulo,
            $tarea->estado->value === 'completada',
            $tarea->fecha_limite?->copy()->startOfDay(),
            false,
            $tarea->proyecto,
            $tarea->prioridad->value,
            null,
        );
    }

    public static function deRecordatorio(Recordatorio $recordatorio): self
    {
        return new self(
            'recordatorio',
            $recordatorio,
            $recordatorio->mensaje,
            $recordatorio->avisado_en !== null,
            $recordatorio->recordar_en,
            $recordatorio->recordar_en !== null,
            null,
            null,
            $recordatorio->tarea?->titulo ?? $recordatorio->descripcion,
        );
    }

    public function id(): int
    {
        return $this->modelo->getKey();
    }

    /** Peso para ordenar por prioridad dentro de un mismo momento (alta primero). */
    public function pesoPrioridad(): int
    {
        return match ($this->prioridad) {
            'alta' => 0,
            'media' => 1,
            'baja' => 2,
            default => 3,
        };
    }

    /** Grupo de tiempo al que pertenece mientras está abierto (vencidas, hoy, manana, semana, despues, sin_fecha). */
    public function grupo(Carbon $hoy): string
    {
        if ($this->cuando === null) {
            return 'sin_fecha';
        }

        $dia = $this->cuando->copy()->startOfDay();

        return match (true) {
            $dia->lt($hoy) => 'vencidas',
            $dia->eq($hoy) => 'hoy',
            $dia->eq($hoy->copy()->addDay()) => 'manana',
            $dia->lte($hoy->copy()->endOfWeek()->startOfDay()) => 'semana',
            default => 'despues',
        };
    }

    /** Texto corto de la fecha: "Hoy 14:30", "Mañana", "Lun 5 oct", "Hace 3 días". Vacío si no tiene fecha. */
    public function etiquetaFecha(Carbon $hoy): string
    {
        if ($this->cuando === null) {
            return '';
        }

        $dia = $this->cuando->copy()->startOfDay();
        $dias = (int) $hoy->diffInDays($dia, false);
        $hora = $this->conHora ? ' '.$this->cuando->format('H:i') : '';

        $texto = match (true) {
            $dias === 0 => 'Hoy',
            $dias === 1 => 'Mañana',
            $dias === -1 => 'Ayer',
            $dias < -1 && $dias >= -30 => 'Hace '.abs($dias).' días',
            default => ucfirst(str_replace('.', '', $dia->locale('es')->isoFormat($dia->year === $hoy->year ? 'ddd D MMM' : 'ddd D MMM YYYY'))),
        };

        return $texto.$hora;
    }

    /** Fecha completa para el atributo title (hover). */
    public function fechaCompleta(): string
    {
        if ($this->cuando === null) {
            return '';
        }

        return ucfirst($this->cuando->locale('es')->isoFormat($this->conHora ? 'dddd D [de] MMMM, HH:mm' : 'dddd D [de] MMMM'));
    }
}
