<?php

namespace App\Models;

use App\Enums\ColorActividad;
use App\Enums\TipoContexto;
use App\Models\Concerns\PerteneceAUsuario;
use Database\Factories\ContextoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

#[Fillable(['nombre', 'tipo', 'contexto_padre_id', 'color'])]
class Contexto extends Model
{
    /** @use HasFactory<ContextoFactory> */
    use HasFactory, PerteneceAUsuario;

    public const SEPARADOR = ' › ';

    protected $table = 'contextos';

    protected function casts(): array
    {
        return [
            'tipo' => TipoContexto::class,
        ];
    }

    public function padre(): BelongsTo
    {
        return $this->belongsTo(self::class, 'contexto_padre_id');
    }

    public function hijos(): HasMany
    {
        return $this->hasMany(self::class, 'contexto_padre_id');
    }

    public function notas(): HasMany
    {
        return $this->hasMany(Nota::class);
    }

    public function cajas(): HasMany
    {
        return $this->hasMany(Caja::class);
    }

    /** Actividades de la agenda: los contextos que tienen un color de la paleta. */
    #[Scope]
    protected function actividades(Builder $query): Builder
    {
        return $query->whereNotNull('color')->orderBy('nombre');
    }

    /** Color de la paleta de actividades, o null si no tiene color o el hex no es de la paleta. */
    public function colorActividad(): ?ColorActividad
    {
        return $this->color === null ? null : ColorActividad::tryFrom(strtolower($this->color));
    }

    #[Scope]
    protected function raices(Builder $query): Builder
    {
        return $query->whereNull('contexto_padre_id');
    }

    /** Ruta completa, por ejemplo "Carrera › Programación 2 › Punteros". */
    public function rutaCompleta(): string
    {
        $partes = [$this->nombre];
        $visitados = [$this->id];
        $actual = $this;

        while ($actual->contexto_padre_id !== null && ! in_array($actual->contexto_padre_id, $visitados, true)) {
            $actual = $actual->padre;

            if ($actual === null) {
                break;
            }

            $visitados[] = $actual->id;
            array_unshift($partes, $actual->nombre);
        }

        return implode(self::SEPARADOR, $partes);
    }

    /** Ids de este contexto y de todos sus descendientes. */
    public function idsConDescendientes(): array
    {
        $porPadre = self::query()->get(['id', 'contexto_padre_id'])->groupBy('contexto_padre_id');

        $ids = [$this->id];
        $pendientes = [$this->id];

        while ($pendientes !== []) {
            $padre = array_pop($pendientes);

            foreach ($porPadre->get($padre, []) as $hijo) {
                if (! in_array($hijo->id, $ids, true)) {
                    $ids[] = $hijo->id;
                    $pendientes[] = $hijo->id;
                }
            }
        }

        return $ids;
    }

    /** Ids de los descendientes (sin incluir el propio contexto). */
    public function idsDescendientes(): array
    {
        return array_values(array_diff($this->idsConDescendientes(), [$this->id]));
    }

    /**
     * Todos los contextos como [id => ruta completa], ordenados por ruta.
     * Resuelve las rutas con una sola consulta.
     *
     * @return Collection<int, string>
     */
    public static function opciones(): Collection
    {
        $todos = self::query()->get(['id', 'nombre', 'contexto_padre_id'])->keyBy('id');

        return $todos
            ->map(function (self $contexto) use ($todos) {
                $partes = [$contexto->nombre];
                $visitados = [$contexto->id];
                $padreId = $contexto->contexto_padre_id;

                while ($padreId !== null && $todos->has($padreId) && ! in_array($padreId, $visitados, true)) {
                    $visitados[] = $padreId;
                    array_unshift($partes, $todos[$padreId]->nombre);
                    $padreId = $todos[$padreId]->contexto_padre_id;
                }

                return implode(self::SEPARADOR, $partes);
            })
            ->sort(SORT_NATURAL | SORT_FLAG_CASE);
    }

    /**
     * Contextos en orden de árbol (cada padre seguido de sus hijos) con su nivel de profundidad.
     *
     * @return Collection<int, array{contexto: self, nivel: int}>
     */
    public static function arbol(): Collection
    {
        $porPadre = self::query()->withCount('notas')->orderBy('nombre')->get()->groupBy('contexto_padre_id');
        $filas = collect();
        $visitados = [];

        $recorrer = function ($padreId, int $nivel) use (&$recorrer, $porPadre, &$filas, &$visitados) {
            foreach ($porPadre->get($padreId, []) as $contexto) {
                if (isset($visitados[$contexto->id])) {
                    continue;
                }

                $visitados[$contexto->id] = true;
                $filas->push(['contexto' => $contexto, 'nivel' => $nivel]);
                $recorrer($contexto->id, $nivel + 1);
            }
        };

        $recorrer(null, 0);

        return $filas;
    }
}
