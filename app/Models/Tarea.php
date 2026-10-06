<?php

namespace App\Models;

use App\Enums\ColorActividad;
use App\Enums\EstadoTarea;
use App\Enums\PrioridadTarea;
use App\Models\Concerns\HeredaColorDeContexto;
use App\Models\Concerns\PerteneceAUsuario;
use Database\Factories\TareaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['titulo', 'descripcion', 'contexto_id', 'fecha_limite', 'prioridad', 'color', 'estado', 'columna_id', 'orden'])]
class Tarea extends Model
{
    /** @use HasFactory<TareaFactory> */
    use HasFactory, HeredaColorDeContexto, PerteneceAUsuario;

    protected function casts(): array
    {
        return [
            'fecha_limite' => 'date',
            'prioridad' => PrioridadTarea::class,
            'estado' => EstadoTarea::class,
            'color' => ColorActividad::class,
        ];
    }

    /**
     * Mantiene sincronizados estado y columna: si cambia la columna, el estado pasa a ser la categoría
     * de esa columna; si cambia solo el estado (Hoy, formulario), la tarea va a la primera columna de esa categoría
     * de SU tablero. Una tarea nueva sin columna cae en "Sin asignar" del tablero principal.
     */
    protected static function booted(): void
    {
        static::saving(function (Tarea $tarea) {
            if ($tarea->columna_id !== null && ($tarea->isDirty('columna_id') || ! $tarea->exists)) {
                $columna = ColumnaTablero::find($tarea->columna_id);

                if ($columna !== null) {
                    $tarea->estado = $columna->categoria;
                    ColumnaTablero::recordarPrevia($tarea, $columna);

                    return;
                }
            }

            $estado = $tarea->estado;

            if ($estado === null) {
                return;
            }

            $columnaActual = $tarea->columna_id !== null ? ColumnaTablero::find($tarea->columna_id) : null;

            if ($columnaActual === null || $columnaActual->categoria !== $estado) {

                // Al reabrir (sale de una completada sin elegir columna) vuelve a la que tenía antes de completarse.
                $reabierta = $columnaActual?->esCompletada() && $estado !== EstadoTarea::Completada
                    ? ColumnaTablero::deReapertura($tarea->columna_previa_id, $columnaActual->tablero_id)
                    : null;
                $destino = $reabierta ?? ColumnaTablero::paraEstado($estado, $columnaActual?->tablero_id, $tarea->user_id);

                $tarea->columna_id = $destino?->id;

                if ($destino !== null) {
                    $tarea->estado = $destino->categoria;
                    ColumnaTablero::recordarPrevia($tarea, $destino);
                }
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
            'contexto_id' => $this->contexto_id,
            'fecha_limite' => $this->fecha_limite?->format('Y-m-d'),
            'prioridad' => $this->prioridad->value,
            'columna_id' => $this->columna_id,
            'color' => $this->color?->value,
            'notas' => $this->notas->map(fn (Nota $nota) => ['id' => $nota->id, 'titulo' => $nota->tituloVisible()])->values()->all(),
        ];
    }

    public function columna(): BelongsTo
    {
        return $this->belongsTo(ColumnaTablero::class, 'columna_id');
    }

    /** Notas vinculadas a la tarea (visibles y abribles desde su tarjeta). */
    public function notas(): BelongsToMany
    {
        return $this->belongsToMany(Nota::class, 'nota_tarea')->withTimestamps();
    }

    public function contexto(): BelongsTo
    {
        return $this->belongsTo(Contexto::class);
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
