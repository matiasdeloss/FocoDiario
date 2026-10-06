<?php

namespace App\Models;

use App\Enums\EstadoTarea;
use App\Models\Concerns\PerteneceAUsuario;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Columna del tablero de tareas. Su categoría (EstadoTarea) es el estado que reciben las tareas
 * que caen en ella; una columna de categoría "completada" marca sus tareas como completadas.
 * Pertenece a un tablero; la columna `fija` es "Sin asignar": primera, de categoría pendiente, sin renombrar, mover ni borrar.
 */
#[Fillable(['nombre', 'categoria', 'posicion', 'tablero_id'])]
class ColumnaTablero extends Model
{
    use PerteneceAUsuario;

    protected $table = 'columnas_tablero';

    /** Una columna creada sin indicar tablero va al principal del usuario. */
    protected static function booted(): void
    {
        static::creating(function (ColumnaTablero $columna) {
            $usuario = $columna->user_id ?? Auth::id();

            if ($columna->tablero_id === null && $usuario !== null) {
                $columna->tablero_id = Tablero::principal($usuario)?->id;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'categoria' => EstadoTarea::class,
            'posicion' => 'integer',
            'fija' => 'boolean',
        ];
    }

    public function tablero(): BelongsTo
    {
        return $this->belongsTo(Tablero::class, 'tablero_id');
    }

    public function tareas(): HasMany
    {
        return $this->hasMany(Tarea::class, 'columna_id');
    }

    public function notas(): HasMany
    {
        return $this->hasMany(Nota::class, 'columna_id');
    }

    public function esCompletada(): bool
    {
        return $this->categoria === EstadoTarea::Completada;
    }

    /**
     * Columna donde cae una tarjeta según su estado, dentro del tablero indicado (por defecto, el principal):
     * la primera de esa categoría ("Sin asignar" para pendiente) o, si no hay, la primera del tablero.
     */
    public static function paraEstado(EstadoTarea $estado, ?int $tableroId = null, ?int $usuarioId = null): ?self
    {
        $tableroId ??= Tablero::principal($usuarioId)?->id;

        if ($tableroId === null) {
            return null;
        }

        $base = static::query()->withoutGlobalScope('usuario')->where('tablero_id', $tableroId);

        return (clone $base)->where('categoria', $estado)->orderBy('posicion')->orderBy('id')->first()
            ?? (clone $base)->orderBy('posicion')->orderBy('id')->first();
    }

    /**
     * ¿Le falta al tablero (por defecto, el principal) una columna de esa categoría? Solo "En progreso" puede faltar: pendiente
     * y completada son obligatorias. Sirve para rechazar un estado pedido en vez de dejar la tarea en "Sin asignar" en silencio.
     */
    public static function faltaCategoria(EstadoTarea $estado, ?int $tableroId = null, ?int $usuarioId = null): bool
    {
        $tableroId ??= Tablero::principal($usuarioId)?->id;

        return $tableroId !== null
            && ! static::query()->withoutGlobalScope('usuario')->where('tablero_id', $tableroId)->where('categoria', $estado)->exists();
    }

    /** Mensaje de validación cuando el tablero no tiene columna del estado pedido. */
    public static function mensajeSinCategoria(EstadoTarea $estado): string
    {
        return "Este tablero no tiene una columna {$estado->etiqueta()}.";
    }

    /**
     * Columna a la que vuelve una tarjeta reabierta: la que tenía antes de completarse, si todavía existe, sigue siendo
     * abierta y es del mismo tablero que la columna actual; si no, null (el llamador usa "Sin asignar" de su tablero).
     */
    public static function deReapertura(?int $previaId, ?int $tableroId): ?self
    {
        if ($previaId === null || $tableroId === null) {
            return null;
        }

        $previa = static::query()->withoutGlobalScope('usuario')->find($previaId);

        return $previa !== null && $previa->tablero_id === $tableroId && ! $previa->esCompletada() ? $previa : null;
    }

    /**
     * Mantiene `columna_previa_id` de una tarea o nota al asignarle la columna `$nueva`: al entrar en una completada desde
     * una abierta recuerda la de origen; al pasar a una abierta la olvida; entre completadas no la toca.
     */
    public static function recordarPrevia(Model $tarjeta, self $nueva): void
    {
        if (! $nueva->esCompletada()) {
            $tarjeta->columna_previa_id = null;

            return;
        }

        $origen = $tarjeta->getOriginal('columna_id');

        if (! $tarjeta->exists || $origen === null || (int) $origen === $nueva->id) {
            return;
        }

        $anterior = static::query()->withoutGlobalScope('usuario')->find($origen);

        if ($anterior !== null && ! $anterior->esCompletada()) {
            $tarjeta->columna_previa_id = $anterior->id;
        }
    }

    /** La "Sin asignar" del tablero indicado (por defecto, el principal): donde caen las tarjetas nuevas. */
    public static function sinAsignarDe(?int $tableroId = null, ?int $usuarioId = null): ?self
    {
        $tableroId ??= Tablero::principal($usuarioId)?->id;

        return $tableroId === null ? null : static::query()->withoutGlobalScope('usuario')->where('fija', true)->where('tablero_id', $tableroId)->first();
    }

    /**
     * Todas las columnas del usuario con su tablero, ordenadas por tablero y por posición: lo que ofrecen los selectores de
     * "tablero y columna" (el tablero va como grupo). Una sola consulta más la de los tableros.
     *
     * @return Collection<int, self>
     */
    public static function paraSelector(): Collection
    {
        $tableros = Tablero::ordenados()->get()->keyBy('id');

        return static::query()->ordenadas()->get()
            ->each(fn (self $columna) => $columna->setRelation('tablero', $tableros->get($columna->tablero_id)))
            ->sortBy(fn (self $c) => sprintf('%08d-%08d-%08d', $c->tablero?->posicion ?? 0, $c->tablero_id, $c->posicion))
            ->values();
    }

    /** ¿Se puede quitar esta columna o cambiarle la categoría sin dejar el tablero sin pendiente/completada? */
    public function categoriaObligatoria(): bool
    {
        return $this->categoria !== EstadoTarea::EnProgreso
            && static::query()->where('tablero_id', $this->tablero_id)->where('categoria', $this->categoria)->whereKeyNot($this->getKey())->doesntExist();
    }

    public function scopeOrdenadas(Builder $consulta): Builder
    {
        return $consulta->orderBy('posicion')->orderBy('id');
    }
}
