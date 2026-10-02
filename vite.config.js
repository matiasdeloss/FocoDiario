import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            // Una entrada común (app) y una por pantalla que tiene CSS o JS propio.
            input: [
                'resources/css/app.css', 'resources/js/app.js',
                'resources/css/hoy.css', 'resources/js/hoy.js',
                'resources/css/agenda.css', 'resources/js/agenda.js', 'resources/js/agenda-dia.js', 'resources/js/agenda-planner-grilla.js',
                'resources/css/tareas.css', 'resources/js/tareas.js',
                'resources/css/tablero.css', 'resources/js/tablero.js',
                'resources/css/notas.css', 'resources/js/notas.js',
                'resources/css/estudio.css',
                'resources/css/recomendaciones.css',
            ],
            refresh: true,
        }),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
