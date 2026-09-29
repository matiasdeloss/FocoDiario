<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Configuración global simple (clave/valor), por ejemplo el título del planner semanal. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ajustes', function (Blueprint $table) {
            $table->string('clave', 60)->primary();
            $table->text('valor')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ajustes');
    }
};
