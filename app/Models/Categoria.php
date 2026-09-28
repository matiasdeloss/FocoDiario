<?php

namespace App\Models;

use App\Enums\TipoCategoria;
use Database\Factories\CategoriaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nombre', 'tipo'])]
class Categoria extends Model
{
    /** @use HasFactory<CategoriaFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'tipo' => TipoCategoria::class,
        ];
    }

    public function bloques(): HasMany
    {
        return $this->hasMany(BloqueTiempo::class);
    }
}
