<?php

namespace App\Models;

use App\Enums\EstadoTarea;
use App\Enums\PrioridadTarea;
use Database\Factories\TareaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['titulo', 'descripcion', 'proyecto', 'fecha_limite', 'prioridad', 'estado', 'columna_id'])]
class Tarea extends Model
{
    /** @use HasFactory<TareaFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'fecha_limite' => 'date',
            'prioridad' => PrioridadTarea::class,
            'estado' => EstadoTarea::class,
        ];
    }

    /**
     * Mantiene sincronizados estado y columna: si cambia la columna, el estado pasa a ser la categoría
     * de esa columna; si cambia solo el estado (Hoy, formulario), la tarea va a la primera columna de esa categoría.
     */
    protected static function booted(): void
    {
        static::saving(function (Tarea $tarea) {
            if ($tarea->columna_id !== null && ($tarea->isDirty('columna_id') || ! $tarea->exists)) {
                $columna = ColumnaTablero::find($tarea->columna_id);

                if ($columna !== null) {
                    $tarea->estado = $columna->categoria;

                    return;
                }
            }

            $estado = $tarea->estado;

            if ($estado === null) {
                return;
            }

            $columnaActual = $tarea->columna_id !== null ? ColumnaTablero::find($tarea->columna_id) : null;

            if ($columnaActual === null || $columnaActual->categoria !== $estado) {
                $tarea->columna_id = ColumnaTablero::paraEstado($estado)?->id;
            }
        });
    }

    /** Datos que el modal "Editar tarea" carga en sus campos (viajan en data-tarea). */
    public function datosModal(): array
    {
        return [
            'id' => $this->id,
            'url' => route('tareas.update', $this),
            'titulo' => $this->titulo,
            'descripcion' => $this->descripcion,
            'proyecto' => $this->proyecto,
            'fecha_limite' => $this->fecha_limite?->format('Y-m-d'),
            'prioridad' => $this->prioridad->value,
            'columna_id' => $this->columna_id,
        ];
    }

    public function columna(): BelongsTo
    {
        return $this->belongsTo(ColumnaTablero::class, 'columna_id');
    }

    #[Scope]
    protected function abiertas(Builder $query): Builder
    {
        return $query->where('estado', '!=', EstadoTarea::Completada);
    }

    public function estaVencida(): bool
    {
        return $this->estado !== EstadoTarea::Completada
            && $this->fecha_limite !== null
            && $this->fecha_limite->lt(today());
    }

    public function recordatorios(): HasMany
    {
        return $this->hasMany(Recordatorio::class);
    }

    public function bloques(): HasMany
    {
        return $this->hasMany(BloqueTiempo::class);
    }
}
