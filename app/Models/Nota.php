<?php

namespace App\Models;

use App\Enums\ColorActividad;
use App\Models\Concerns\HeredaColorDeContexto;
use App\Models\Concerns\PerteneceAUsuario;
use Database\Factories\NotaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

#[Fillable(['titulo', 'contenido', 'contexto_id', 'fecha', 'fijada', 'color', 'columna_id', 'orden', 'oculta'])]
class Nota extends Model
{
    /** @use HasFactory<NotaFactory> */
    use HasFactory, HeredaColorDeContexto, PerteneceAUsuario;

    protected $table = 'notas';

    /** Una nota nueva sin columna cae en "Sin asignar" del tablero principal (las notas también son tarjetas). */
    protected static function booted(): void
    {
        static::saving(function (Nota $nota) {
            if ($nota->columna_id === null && ! $nota->exists) {
                // Sin usuario (ni propio ni de la sesión) no se asigna columna: nunca se busca sin acotar.
                $usuario = $nota->user_id ?? Auth::id();

                if ($usuario !== null) {
                    $nota->columna_id = ColumnaTablero::sinAsignarDe(null, $usuario)?->id;
                }
            }

            if ($nota->exists && $nota->columna_id !== null && $nota->isDirty('columna_id')) {
                $columna = ColumnaTablero::query()->withoutGlobalScope('usuario')->find($nota->columna_id);

                if ($columna !== null) {
                    ColumnaTablero::recordarPrevia($nota, $columna);
                }
            }
        });
    }

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'fijada' => 'boolean',
            'oculta' => 'boolean',
            'color' => ColorActividad::class,
        ];
    }

    /** Título a mostrar: el propio o, en notas viejas, el contenido recortado. */
    public function tituloVisible(): string
    {
        $titulo = trim((string) $this->titulo);

        return $titulo !== '' ? $titulo : Str::limit(trim(preg_replace('/\s+/', ' ', (string) $this->contenido)), 60);
    }

    public function columna(): BelongsTo
    {
        return $this->belongsTo(ColumnaTablero::class, 'columna_id');
    }

    /** Tareas a las que está vinculada (una nota puede servir a varias). */
    public function tareas(): BelongsToMany
    {
        return $this->belongsToMany(Tarea::class, 'nota_tarea')->withTimestamps();
    }

    /** Datos que el modal "Editar nota" carga en sus campos (viajan en data-nota). */
    public function datosEdicion(): array
    {
        return [
            'url' => route('notas.update', $this),
            'titulo' => $this->titulo,
            'contenido' => $this->contenido,
            'contexto_id' => $this->contexto_id,
            'fecha' => $this->fecha?->format('Y-m-d'),
            'color' => $this->color?->value,
            'fijada' => $this->fijada,
            'columna_id' => $this->columna_id,
        ];
    }

    /** Una nota no tiene estado propio: está completada cuando su columna del tablero es de categoría "completada". */
    public function estaCompletada(): bool
    {
        // Con conEstadoDeColumna() ya viene calculado en la misma consulta (sin cargar la columna de cada nota).
        if (array_key_exists('en_completada', $this->attributes)) {
            return (bool) $this->attributes['en_completada'];
        }

        return $this->columna?->esCompletada() ?? false;
    }

    /** Agrega `en_completada` (si la columna de la nota es de tipo completada) a la consulta, para listar sin una consulta por nota. */
    #[Scope]
    protected function conEstadoDeColumna(Builder $query): Builder
    {
        return $query->addSelect('notas.*')->selectSub(
            ColumnaTablero::query()->selectRaw("categoria = 'completada'")->whereColumn('columnas_tablero.id', 'notas.columna_id')->limit(1),
            'en_completada',
        );
    }

    public function contexto(): BelongsTo
    {
        return $this->belongsTo(Contexto::class);
    }

    /** Bandeja de entrada: notas sin destino. */
    #[Scope]
    protected function sinContexto(Builder $query): Builder
    {
        return $query->whereNull('contexto_id');
    }

    /** Notas de un contexto y de todos sus descendientes. */
    #[Scope]
    protected function delContexto(Builder $query, Contexto $contexto): Builder
    {
        return $query->whereIn('contexto_id', $contexto->idsConDescendientes());
    }

    /** Solo para la pantalla Notas: las ocultas no se listan ahí (en el resto de la app se ven igual). */
    #[Scope]
    protected function sinOcultar(Builder $query): Builder
    {
        return $query->where('oculta', false);
    }

    #[Scope]
    protected function soloOcultas(Builder $query): Builder
    {
        return $query->where('oculta', true);
    }

    /** Fijadas primero, después las más nuevas. */
    #[Scope]
    protected function ordenadas(Builder $query): Builder
    {
        return $query->orderByDesc('fijada')->orderByDesc('created_at')->orderByDesc('id');
    }
}
