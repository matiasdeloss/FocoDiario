<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Dato de un usuario: cada consulta ve solo lo del usuario de la sesión (también el route model binding,
 * que así da 404 con ids ajenos) y lo nuevo se guarda a su nombre.
 * Sin sesión (consola, seeders, migraciones) no se filtra: ahí el user_id se indica a mano.
 */
trait PerteneceAUsuario
{
    public static function bootPerteneceAUsuario(): void
    {
        static::addGlobalScope('usuario', function (Builder $consulta) {
            if (Auth::check()) {
                $consulta->where($consulta->qualifyColumn('user_id'), Auth::id());
            } elseif (! app()->runningInConsole()) {
                // Una petición web sin usuario no ve nada (falla cerrado, nunca "todo").
                $consulta->whereRaw('1 = 0');
            }
        });

        static::creating(function ($modelo) {
            if ($modelo->user_id === null && Auth::check()) {
                $modelo->user_id = Auth::id();
            }
        });
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
