<?php

use App\Http\Controllers\HoyController;
use App\Http\Controllers\RecordatorioController;
use App\Http\Controllers\RegistroController;
use App\Http\Controllers\TareaController;
use Illuminate\Support\Facades\Route;

Route::get('/', HoyController::class)->name('hoy');

Route::resource('tareas', TareaController::class);
Route::resource('recordatorios', RecordatorioController::class);
Route::resource('registro', RegistroController::class);

// Acciones extra de los módulos
Route::patch('tareas/{tarea}/estado', [TareaController::class, 'cambiarEstado'])->name('tareas.estado');
Route::patch('recordatorios/{recordatorio}/avisar', [RecordatorioController::class, 'avisar'])->name('recordatorios.avisar');
Route::put('dia', [RegistroController::class, 'guardarDia'])->name('registro.dia');
