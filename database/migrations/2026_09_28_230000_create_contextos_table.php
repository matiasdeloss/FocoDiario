<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contextos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('tipo', 20);
            $table->foreignId('contexto_padre_id')->nullable()->constrained('contextos')->nullOnDelete();
            $table->string('color', 7)->nullable();
            $table->timestamps();

            $table->unique(['contexto_padre_id', 'nombre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contextos');
    }
};
