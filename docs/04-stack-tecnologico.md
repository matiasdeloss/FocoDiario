# 4. Stack tecnológico

## Decisión
| Capa | Elección | Motivo |
|---|---|---|
| Backend | Laravel 13 (PHP 8.4) | Ya instalado y usado en `Mis notas`. Migraciones, validación y rutas resueltas. Empezó en Laravel 12 y se actualizó. |
| Base de datos local | MySQL, desde Laragon | Laragon aporta solo MySQL (puerto 3306). Su servidor web queda apagado para no chocar con Herd en el puerto 80. |
| Base de datos en producción | PostgreSQL en Neon | Plan gratuito persistente y sin tarjeta. Conexión directa (sin pooler) para que corran las migraciones. |
| Servidor web local | Laravel Herd | Sirve `focodiario.test` con PHP 8.4. Se registra con `herd link`, sin mover archivos. |
| Publicación | Docker + FrankenPHP en Render | Plan gratuito con auto-deploy desde `main`. El `entrypoint` corre las migraciones al iniciar. |
| Vistas | Blade + HTMX | Páginas renderizadas en el servidor, con interacciones sin recargar y sin framework de JS. |
| Estilos | Bootstrap 5 | Mismo criterio que otros proyectos, responsive de entrada. |
| Calendario y agenda | FullCalendar + Gridstack | Vistas de calendario listas y cajas reubicables en la agenda semanal. |
| Temporizador | JS vanilla | El Pomodoro corre en el navegador; el servidor solo guarda los ciclos completados. |
| Build | Vite | Viene con Laravel. |
| Después | PWA | Instalable en el celular y con notificaciones para recordatorios. |

## Alternativas descartadas
- **PWA en JS puro con IndexedDB:** funciona offline, pero el respaldo y las consultas de estadísticas habría que resolverlos a mano.
- **Livewire:** menos JS, pero suma otra herramienta a aprender sin necesidad para este alcance.
- **SQLite:** sirve, pero como ya usás Laragon, MySQL evita instalar nada y permite ver los datos con HeidiSQL.

## Estructura prevista
```
FocoDiario/
├── app/
│   ├── Enums/           # tipos y estados con etiqueta()
│   ├── Models/          # Categoria, Tarea, Recordatorio, BloqueTiempo, Contexto, Nota, SesionEstudio, IntervaloEstudio
│   ├── Http/Controllers/ y Http/Requests/
│   └── Services/        # ResumenRegistro, ResumenEstudio, RegistroEstudio y Recomendaciones/ (una clase por regla)
├── database/migrations/
├── resources/views/     # Blade + fragments HTMX
├── resources/js/        # pomodoro.js y pomodoro-logica.js (fases, con pruebas en Node)
├── config/estudio.php   # fichas de métodos de estudio y fuentes
└── docs/
```

## Entidades iniciales
Tomadas de `02-requisitos-y-funcionalidades.md`:
- `categorias`: nombre, tipo (productiva / ocio / descanso).
- `tareas`: título, proyecto, fecha límite, prioridad, estado.
- `recordatorios`: fecha y hora, mensaje, tarea opcional.
- `bloques_tiempo`: inicio, fin, categoría, tarea opcional, origen (manual / pomodoro), concentración (1 a 5, fase 3).
- `contextos`: nombre, tipo, contexto padre, color.
- `notas`: contenido, contexto opcional, fecha opcional, fijada.
- `sesiones_estudio` e `intervalos_estudio`: sesiones de Pomodoro y sus fases (foco, descanso, tiempo libre).
- (`dias` se creó y luego se eliminó: el sueño no se registra a mano.)

## Pantallas del MVP
1. Hoy: nota rápida, recomendaciones, métricas, próximas tareas y recordatorios.
2. Tareas: lista y alta, con filtros por proyecto y estado.
3. Recordatorios.
4. Registro del día: bloques y resumen de horas.
5. Notas y Contextos.
6. Recomendaciones.
7. Estudio: temporizador, historial y métodos.

## Pasos para arrancar
1. [x] Proyecto Laravel creado, base MySQL `focodiario` y `.env` configurado.
2. [x] Sitio enlazado en Herd (`focodiario.test` responde).
3. [x] Migraciones, modelos, enums y factories de las entidades.
4. [x] Módulo Tareas (CRUD con HTMX).
5. [x] Pomodoro (JS vanilla) con registro de ciclos completados, en la vista Estudio.
6. [x] Registro del día y resumen de horas.
7. [x] Contextos, notas rápidas y recomendaciones.
8. [x] Calendario, agenda semanal y tablero kanban.
9. [x] Modo invitado, cuentas y datos por usuario.
10. [x] Publicación en Render con PostgreSQL en Neon.
11. [ ] Contexto en las tareas, estadísticas semanales y exportación de datos desde la app.

## Ubicación del proyecto (hecho)
El código vive en `Proyectos\FocoDiario`. Herd lo sirve en `http://focodiario.test` mediante `herd link focodiario`. La base `focodiario` está en el MySQL de Laragon (usuario `root`, sin contraseña, solo local) y las migraciones base ya corrieron.

## Pendiente de decidir
- Exportación de datos desde la app para cada usuario (CSV o JSON). El respaldo de la base de producción ya está resuelto con un `pg_dump` semanal fuera del repositorio.
- Si la configuración del Pomodoro, hoy en el navegador (localStorage), pasa a la base de datos.
- Si el tiempo libre debe seguir registrándose cuando se cierra el navegador (hoy ese intervalo se pierde).
