<?php

namespace App\Models;

use App\Enums\EstadoTarea;
use App\Models\Concerns\PerteneceAUsuario;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Columna del tablero de tareas. Su categoría (EstadoTarea) es el estado que reciben las tareas
 * que caen en ella; una columna de categoría "completada" marca sus tareas como completadas.
 */
#[Fillable(['nombre', 'categoria', 'posicion'])]
class ColumnaTablero extends Model
{
    use PerteneceAUsuario;

    protected $table = 'columnas_tablero';

    protected function casts(): array
    {
        return [
            'categoria' => EstadoTarea::class,
            'posicion' => 'integer',
        ];
    }

    public function tareas(): HasMany
    {
        return $this->hasMany(Tarea::class, 'columna_id');
    }

    public function esCompletada(): bool
    {
        return $this->categoria === EstadoTarea::Completada;
    }

    /** Columna donde cae una tarea según su estado: la primera de esa categoría, o la primera del tablero. */
    public static function paraEstado(EstadoTarea $estado): ?self
    {
        return static::query()->where('categoria', $estado)->orderBy('posicion')->orderBy('id')->first()
            ?? static::query()->orderBy('posicion')->orderBy('id')->first();
    }

    /** ¿Se puede quitar esta columna o cambiarle la categoría sin dejar el tablero sin pendiente/completada? */
    public function categoriaObligatoria(): bool
    {
        return $this->categoria !== EstadoTarea::EnProgreso
            && static::query()->where('categoria', $this->categoria)->whereKeyNot($this->getKey())->doesntExist();
    }

    public function scopeOrdenadas(Builder $consulta): Builder
    {
        return $consulta->orderBy('posicion')->orderBy('id');
    }
}
