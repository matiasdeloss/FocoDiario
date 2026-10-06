<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAUsuario;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tablero kanban de un usuario. Cada usuario tiene uno o más; exactamente uno es el principal: lo que se crea
 * fuera del tablero (Hoy, calendario, formularios) cae en la columna "Sin asignar" del principal.
 */
#[Fillable(['nombre', 'principal', 'posicion'])]
class Tablero extends Model
{
    use PerteneceAUsuario;

    protected $table = 'tableros';

    protected function casts(): array
    {
        return [
            'principal' => 'boolean',
            'posicion' => 'integer',
        ];
    }

    public function columnas(): HasMany
    {
        return $this->hasMany(ColumnaTablero::class, 'tablero_id')->orderBy('posicion')->orderBy('id');
    }

    /** La columna fija "Sin asignar" (la primera del tablero). */
    public function sinAsignar(): ?ColumnaTablero
    {
        return ColumnaTablero::query()->where('tablero_id', $this->id)->where('fija', true)->first();
    }

    public function scopeOrdenados(Builder $consulta): Builder
    {
        return $consulta->orderBy('posicion')->orderBy('id');
    }

    /**
     * El tablero principal del usuario de la sesión o, si se indica `$usuarioId` (consola, seeders: sin sesión), el de ese usuario.
     */
    public static function principal(?int $usuarioId = null): ?self
    {
        $consulta = $usuarioId === null ? static::query() : static::query()->withoutGlobalScope('usuario')->where('user_id', $usuarioId);

        return (clone $consulta)->where('principal', true)->first() ?? (clone $consulta)->ordenados()->first();
    }

    /**
     * Hace principal a este tablero y deja a los demás como no principales (siempre hay exactamente uno).
     */
    public function hacerPrincipal(): void
    {
        static::query()->where('principal', true)->whereKeyNot($this->getKey())->update(['principal' => false]);
        $this->forceFill(['principal' => true])->save();
    }

    /** Crea el tablero con su columna fija "Sin asignar" y las columnas de fábrica indicadas ([nombre, categoria]). */
    public static function crearConColumnas(int $usuarioId, string $nombre, bool $principal, array $columnas, int $posicion): self
    {
        $tablero = new self(['nombre' => $nombre, 'principal' => $principal, 'posicion' => $posicion]);
        $tablero->user_id = $usuarioId;
        $tablero->save();

        $todas = [['Sin asignar', \App\Enums\EstadoTarea::Pendiente, true], ...array_map(fn ($c) => [$c[0], $c[1], false], $columnas)];

        foreach ($todas as $indice => [$nombreColumna, $categoria, $fija]) {
            $columna = new ColumnaTablero(['nombre' => $nombreColumna, 'categoria' => $categoria, 'posicion' => $indice, 'tablero_id' => $tablero->id]);
            $columna->fija = $fija;
            $columna->user_id = $usuarioId;
            $columna->save();
        }

        return $tablero;
    }
}
