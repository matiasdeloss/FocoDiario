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
