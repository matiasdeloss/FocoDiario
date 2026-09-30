<?php

use Illuminate\Support\Facades\Schedule;

// Invitados abandonados: en el servidor hace falta el cron de "php artisan schedule:run" cada minuto.
Schedule::command('foco:limpiar-invitados')->dailyAt('04:00');
