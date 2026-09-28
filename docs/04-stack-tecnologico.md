# 4. Stack tecnológico

## Decisión
| Capa | Elección | Motivo |
|---|---|---|
| Backend | Laravel 12 (PHP 8.4) | Ya instalado y usado en `Mis notas`. Migraciones, validación y rutas resueltas. |
| Base de datos | MySQL, desde Laragon | Laragon aporta solo MySQL (puerto 3306). Su servidor web queda apagado para no chocar con Herd en el puerto 80. |
| Servidor web local | Laravel Herd | Sirve `focodiario.test` con PHP 8.4. Se registra con `herd link`, sin mover archivos. |
| Vistas | Blade + HTMX | Páginas renderizadas en el servidor, con interacciones sin recargar y sin framework de JS. |
| Estilos | Bootstrap 5 | Mismo criterio que otros proyectos, responsive de entrada. |
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
│   ├── Models/          # Categoria, Tarea, Recordatorio, BloqueTiempo, Dia
│   ├── Http/Controllers/
│   └── Services/        # cálculos de estadísticas y cronotipo
├── database/migrations/
├── resources/views/     # Blade + fragments HTMX
├── resources/js/        # pomodoro.js
└── docs/
```

## Entidades iniciales
Tomadas de `02-requisitos-y-funcionalidades.md`:
- `categorias`: nombre, tipo (productiva / ocio / descanso).
- `tareas`: título, proyecto, fecha límite, prioridad, estado.
- `recordatorios`: fecha y hora, mensaje, tarea opcional.
- `bloques_tiempo`: inicio, fin, categoría, tarea opcional, origen (manual / pomodoro), concentración (1 a 5, fase 3).
- `dias`: fecha, hora de despertar, hora de dormir.

## Pantallas del MVP
1. Hoy: tareas del día, Pomodoro y registro rápido de bloques.
2. Tareas: lista y alta, con filtros por proyecto y estado.
3. Recordatorios.
4. Registro del día: bloques, hora de despertar y de dormir.

## Pasos para arrancar
1. [x] Proyecto Laravel creado, base MySQL `focodiario` y `.env` configurado.
2. [x] Sitio enlazado en Herd (`focodiario.test` responde).
3. [x] Migraciones, modelos, enums y factories de las entidades.
4. [ ] Módulo Tareas (CRUD con HTMX).
5. [ ] Pomodoro (JS vanilla) con registro de ciclos completados.
6. [ ] Registro del día y resumen simple de horas.

## Ubicación del proyecto (hecho)
El código vive en `Proyectos\FocoDiario`. Herd lo sirve en `http://focodiario.test` mediante `herd link focodiario`. La base `focodiario` está en el MySQL de Laragon (usuario `root`, sin contraseña, solo local) y las migraciones base ya corrieron.

## Pendiente de decidir
- Cómo se hará el respaldo de la base (exportar a `.sql` o a JSON).
