<?php

namespace App\Services\Hoy;

use App\Enums\EstadoTarea;
use App\Models\Recordatorio;
use App\Models\Tarea;

/** Datos de las tarjetas Tareas abiertas y Recordatorios de Hoy (los usan la pantalla y la captura rápida). */
class ListasHoy
{
    private const TAREAS_ABIERTAS = 8;

    private const TAREAS_COMPLETADAS = 3;

    private const RECORDATORIOS = 5;

    /** @return array<string, mixed> */
    public function tareas(): array
    {
        return [
            'tareasAbiertas' => Tarea::abiertas()
                ->orderByRaw("case prioridad when 'alta' then 0 when 'media' then 1 else 2 end")
                ->orderByRaw('fecha_limite is null')
                ->orderBy('fecha_limite')
                ->orderBy('id')
                ->limit(self::TAREAS_ABIERTAS)
                ->get(),
            'tareasCompletadas' => Tarea::where('estado', EstadoTarea::Completada)
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->limit(self::TAREAS_COMPLETADAS)
                ->get(),
            'totalPendientes' => Tarea::abiertas()->count(),
        ];
    }

    /** @return array<string, mixed> */
    public function recordatorios(): array
    {
        return [
            'recordatorios' => Recordatorio::pendientes()->conFecha()->orderBy('recordar_en')->limit(self::RECORDATORIOS)->get(),
            'recordatoriosSinFecha' => Recordatorio::pendientes()->sinFecha()->count(),
        ];
    }
}
