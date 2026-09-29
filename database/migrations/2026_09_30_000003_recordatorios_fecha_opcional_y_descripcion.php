<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recordatorios', function (Blueprint $table) {
            // Un recordatorio "por ubicar" todavía no tiene fecha.
            $table->dateTime('recordar_en')->nullable()->change();
            $table->text('descripcion')->nullable()->after('mensaje');
        });
    }

    public function down(): void
    {
        // Los recordatorios sin fecha reciben la fecha de creación para poder volver a NOT NULL.
        DB::table('recordatorios')->whereNull('recordar_en')->update(['recordar_en' => DB::raw('created_at')]);

        Schema::table('recordatorios', function (Blueprint $table) {
            $table->dropColumn('descripcion');
            $table->dateTime('recordar_en')->nullable(false)->change();
        });
    }
};
