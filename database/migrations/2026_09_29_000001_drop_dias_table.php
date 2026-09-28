<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('dias');
    }

    public function down(): void
    {
        Schema::create('dias', function (Blueprint $table) {
            $table->id();
            $table->date('fecha')->unique();
            $table->dateTime('desperto_a')->nullable();
            $table->dateTime('durmio_a')->nullable();
            $table->timestamps();
        });
    }
};
