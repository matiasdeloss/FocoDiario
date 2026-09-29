<?php

namespace App\Models;

use Database\Factories\RecordatorioFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tarea_id', 'mensaje', 'descripcion', 'recordar_en', 'avisado_en'])]
class Recordatorio extends Model
{
    /** @use HasFactory<RecordatorioFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'recordar_en' => 'datetime',
            'avisado_en' => 'datetime',
        ];
    }

    #[Scope]
    protected function pendientes(Builder $query): Builder
    {
        return $query->whereNull('avisado_en');
    }

    /** Recordatorios con fecha y hora asignadas. */
    #[Scope]
    protected function conFecha(Builder $query): Builder
    {
        return $query->whereNotNull('recordar_en');
    }

    /** Recordatorios "por ubicar": todavía sin fecha. */
    #[Scope]
    protected function sinFecha(Builder $query): Builder
    {
        return $query->whereNull('recordar_en');
    }

    public function tarea(): BelongsTo
    {
        return $this->belongsTo(Tarea::class);
    }
}
