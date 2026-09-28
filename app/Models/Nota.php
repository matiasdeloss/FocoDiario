<?php

namespace App\Models;

use Database\Factories\NotaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['contenido', 'contexto_id', 'fecha', 'fijada'])]
class Nota extends Model
{
    /** @use HasFactory<NotaFactory> */
    use HasFactory;

    protected $table = 'notas';

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'fijada' => 'boolean',
        ];
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

    /** Fijadas primero, después las más nuevas. */
    #[Scope]
    protected function ordenadas(Builder $query): Builder
    {
        return $query->orderByDesc('fijada')->orderByDesc('created_at')->orderByDesc('id');
    }
}
