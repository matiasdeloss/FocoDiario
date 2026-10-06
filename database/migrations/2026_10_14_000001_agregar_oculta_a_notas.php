<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Una nota oculta deja de verse solo en la pantalla Notas: sigue en contextos, tablero, calendario y Hoy.
 * Las notas existentes quedan visibles (false por defecto).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notas', function (Blueprint $table) {
            $table->boolean('oculta')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('notas', function (Blueprint $table) {
            $table->dropColumn('oculta');
        });
    }
};
