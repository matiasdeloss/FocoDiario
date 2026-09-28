<?php

namespace App\Models;

use Database\Factories\DiaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['fecha', 'desperto_a', 'durmio_a'])]
class Dia extends Model
{
    /** @use HasFactory<DiaFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'desperto_a' => 'datetime',
            'durmio_a' => 'datetime',
        ];
    }
}
