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

    public function tareas(): HasMany
    {
        return $this->hasMany(Tarea::class);
    }

    public function cajas(): HasMany
    {
        return $this->hasMany(Caja::class);
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

    /** Id del contexto del usuario con ese nombre (sin distinguir mayúsculas), o null; sirve a los enlaces viejos ?proyecto=NOMBRE. */
    public static function idPorNombre(?string $nombre): ?int
    {
        $nombre = trim((string) $nombre);

        return $nombre === '' ? null : self::query()->whereRaw('LOWER(nombre) = LOWER(?)', [$nombre])->orderBy('id')->value('id');
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

    /** Todos los contextos del usuario con lo mínimo para armar rutas, colores y filtros: una sola consulta que se puede reutilizar. */
    public static function todos(): Collection
    {
        return self::query()->get(['id', 'nombre', 'tipo', 'color', 'contexto_padre_id']);
    }

    /**
     * Todos los contextos como [id => ruta completa], ordenados por ruta.
     * Resuelve las rutas con una sola consulta (o con `$contextos`, si el llamador ya los cargó con todos()).
     *
     * @param  Collection<int, self>|null  $contextos
     * @return Collection<int, string>
     */
    public static function opciones(?Collection $contextos = null): Collection
    {
        return self::rutas($contextos ?? self::query()->get(['id', 'nombre', 'contexto_padre_id']));
    }

    /**
     * Ruta completa de cada contexto de `$contextos` (que debe incluir a sus padres), ordenadas.
     *
     * @param  Collection<int, self>  $contextos
     * @return Collection<int, string>
     */
    private static function rutas(Collection $contextos): Collection
    {
        $todos = $contextos->keyBy('id');

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
     * Los mismos contextos de opciones() agrupados por tipo, para los selectores de las tareas:
     * [etiqueta del tipo => [id => ruta completa]], en el orden del enum y sin los tipos vacíos.
     * Con `$soloConTareas` deja solo los contextos que tienen alguna tarea, ellos o sus descendientes (para los filtros,
     * que incluyen a los descendientes). Con `$contextos` (ver todos()) no vuelve a consultarlos.
     *
     * @param  Collection<int, self>|null  $contextos
     * @return Collection<string, Collection<int, string>>
     */
    public static function opcionesPorTipo(bool $soloConTareas = false, ?Collection $contextos = null): Collection
    {
        $contextos ??= self::query()->get(['id', 'nombre', 'tipo', 'contexto_padre_id']);
        $rutas = self::rutas($contextos);

        if ($soloConTareas) {
            $porId = $contextos->keyBy('id');
            $visibles = [];

            foreach (Tarea::query()->whereNotNull('contexto_id')->distinct()->pluck('contexto_id') as $id) {
                // El contexto de la tarea y sus ancestros.
                while ($id !== null && $porId->has($id) && ! isset($visibles[$id])) {
                    $visibles[$id] = true;
                    $id = $porId[$id]->contexto_padre_id;
                }
            }

            $rutas = $rutas->only(array_keys($visibles));
        }

        $tipos = $contextos->pluck('tipo', 'id');

        return collect(TipoContexto::cases())
            ->mapWithKeys(fn (TipoContexto $tipo) => [
                $tipo->etiqueta() => $rutas->filter(fn (string $ruta, int $id) => $tipos[$id] === $tipo),
            ])
            ->filter(fn (Collection $grupo) => $grupo->isNotEmpty());
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
