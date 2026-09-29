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
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['titulo', 'descripcion', 'proyecto', 'fecha_limite', 'prioridad', 'estado'])]
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
