# Toasts estandarizados

## Objective
Every confirmation or system message ("Tarea creada", "Nota guardada", save errors, offline, undo, reminders, guest notice) is shown through ONE toast system with a standard variant per kind of message.

## Current state (exploration 2026-10-01)
- `resources/js/avisos.js` (`mostrarAviso`) is already the floating toast, but only with info/error variants. Its container is created lazily.
- Second system: the server flash `session('estado')` is rendered as an inline banner `.aviso-foco` in `layouts/app.blade.php:36-38`.
- Duplicated local toasts: `calendario.js:46` (`#calendario-aviso`), `tablero.js:23` (`#tablero-aviso`). Inline messages: `hoy.js` `[data-mensaje]`, `#nota-rapida-aviso` (OOB `.hoy-aviso`), `tareas.js`.
- No HX-Trigger message mechanism exists. The flash/HTMX/JSON branching is duplicated across NotaController, TareaController, RecordatorioController, ColumnaTableroController and CuentaController.

## Scope
- Variants: `exito`, `info`, `aviso`, `error`, `recordatorio`. Undo uses `info` plus a "Deshacer" action.
- One static container in the layout. One JS API in `avisos.js`.
- Server: typed flash (keep the key `estado`, add the type), converted to a toast on load. HTMX responses via `HX-Trigger` `mostrar-aviso`. A small shared helper (trait or `Support` class).
- Migrate every module: Hoy, Tareas, Recordatorios, Tablero, Notas, Contextos, Registro, Agenda/actividades, Estudio, Calendario, Cuenta.
- Theme toggle toast was added and then REMOVED at the user's request (2026-10-01): switching theme shows no toast.
- Toast position: top center (user request); entrance animation `foco-baja`.
- Stay inline: field validation, autosave indicators (Guardando/Guardado), empty states, visually-hidden live regions, the confirm dialog, the Pomodoro "terminada" panel. An autosave final failure also raises an error toast.

## Constraints
- No commits unless the user asks.
- Spanish copy and comments, like the existing code. Only theme tokens (light and dark).
- CSP uses nonces: use data attributes or JSON script blocks, not inline handlers.
- Keep the accessibility of the current toasts (role status/alert, hover/focus pause, close button).

## Route
- T1 delegated direct (writer trigger: many files across PHP, Blade, JS, CSS).

## TDD
Off. Checks: `npm run build`, `npm test`, `php artisan test`.

## Tasks
- [x] T1 Toast core, server helper, and migration of all modules. JS `aviso.exito/info/aviso/error/recordatorio` in `avisos.js` + `avisos-logica.js`; PHP `App\Support\Aviso` (flash keeps `estado` + `estado_tipo`, `enHtmx` sets HX-Trigger `mostrar-aviso`); container is a `popover` in the layout; theme toggle toast. Left silent: HTMX "Estado actualizado." and reminder check/uncheck. Evidence: build OK, npm test 18/18, php artisan test 374 passed (writer); build + AvisosTest 13/13 re-run by parent.
- [x] T2 Visual check of the variants in light and dark: PASS (position top-center, stacking, no theme toast, nav, quick note). Fixed after check: toast under open modal (container moves into the open dialog, back to body on close); dark `--color-nota-*` tokens were missing from the `[data-theme=dark]` block. Re-check PASS. Open: test data in local MySQL (users id > 18 plus 2 older guests) awaits the user's OK to delete.

## Also done in this round (small, inline)
- Nav: account name 15px/600 in text color, user icon 22px in accent; "Salir" is a bordered pill with visible text and a danger hover. Build OK, tests Nav|Cuenta|Hoy 76 passed.
- Quick note: no jump on focus, soft rounded focus ring on the title.

## Next step
T1.
