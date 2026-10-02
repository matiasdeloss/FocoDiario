# Notas, planner semanal, calendario y nota rápida

## Objective
Four UI improvements requested on 2026-10-01, keeping the calendar as the system's core (every day/time relates to the calendar).

## Scope
- T1 Notas: the color filter pills on `notas/index.blade.php` show only the color dot; the name stays as `aria-label`/`title`.
- T2 Planner semanal: the 7 day cards (and the week zone boxes, Notas/Pendiente) become movable and resizable boxes in a GridStack grid, like the day view (`agenda-dia.js`). Layout persists per user and is the same every week (assumption, not asked). Keyboard/mobile behavior mirrors the day view.
- T3 Calendario: new type filter "Ver planner semanal" (`planner`) alongside tareas, recordatorios, notas, estudio. Events come from day boxes (`cajas.fecha` + `hora_inicio`/`hora_fin`; all-day when no time). Draggable/resizable in the calendar (user decision 2026-10-01): changing day/time updates `cajas.fecha`/`hora_inicio`/`hora_fin`, so the planner and day view reflect it automatically. Detail links to the agenda day.
- T4 Nota rápida (Hoy): when "Tarea" is selected, show the same fields as the edit-task modal (proyecto, fecha límite, color, prioridad, columna, comentario). Prefer a shared `tareas/_campos` partial with an id prefix.
- T5 Visual check in light and dark of all views (includes pending T2 of `sistema-visual-modo-oscuro.md`) and of these four changes. Done by Claude with a temporary browser profile, never touching the user's browser.

## Constraints
- No commits unless the user asks (user rule overrides ODD work-unit commits).
- Spanish comments/identifiers as in the existing code; UI copy in Spanish.
- Light/dark tokens only, no hardcoded colors.

## Routes
- T1+T2 delegated direct (writer trigger: Blade + CSS + JS + migration/controller).
- T3+T4 delegated direct (writer trigger: service, request, Blade, JS).

## TDD
Off (no configuration). Checks: `npm run build`, `npm test`, `php artisan test`.

## Tasks
- [x] T1 Notas filter pills dot only (40px round pill, ring for active).
- [x] T2 Planner semanal movable/resizable day cards. Layout in `ajustes` key `planner.layout` via `PlannerLayout` service, `PATCH agenda/layout`, `resources/js/agenda-planner-grilla.js`. Evidence: build OK, npm test 12/12, php artisan test 341 passed (writer); AgendaPlannerTest 22/22 re-run by parent. Browser check pending (T5).
- [x] T3 Calendario planner filter ("Planner semanal" label), drag/resize via `PATCH calendario/planner/{caja}/mover`, places without overlap on target day; click goes to agenda day.
- [x] T4 Nota rápida "Más detalles" panel for tarea (proyecto, prioridad, columna) via shared `tareas/_campo-extra.blade.php`; color now also saved for tareas. Evidence T3+T4: build OK, npm test 17/17, php artisan test 360 passed, contraste-tipos 0 failures (writer); CalendarioPlannerTest+CapturaRapidaTest 39/39 re-run by parent.
- [x] T5 Visual check both themes (Playwright, temp profile, guest mode): all items PASS. Found and fixed: Hoy 500 when the week has planner boxes (`SemanaHoy::ETIQUETAS_TIPO` lacked `planner`; regression test `HoyTest::test_la_semana_resume_las_cajas_del_planner`, RED observed then GREEN, full suite 361 passed); dark dialog backdrop lightened the page (new `--color-velo` token). Not checked: logged-in nav, real reminder toast. Open: Durazno/Terracota look alike in dark; day count clipped in very small planner cards; test data from two guest users left in the local DB (MySQL went down before cleanup).

## Review findings (/code-review high, 2026-10-01) — to fix after the toast writer finishes
- [x] R1 calendario.js datosPlanner sends the display-only 1h end for boxes without hora_fin (persists an end never chosen; 23:30 → 23:59).
- [x] R2 eventoPlanner lacks `ev-hecho` for done boxes (calendar + Hoy week show them pending).
- [x] R3 CalendarioController move: y = max(y+alto) not clamped to Caja::MAX_FILA (breaks day layout save).
- [x] R4 planner type missing in hoy.js ETIQUETAS_EVENTO and hoy/_semana.blade.php $tiposEvento.
- [x] R5 SemanaHoy::ETIQUETAS_SIN_HORA lacks planner (empty label for all-day boxes).
- [x] R6 MoverCajaPlannerRequest::authorize strict user_id compare (403 risk); route binding already scopes.
- [x] R7 PlannerLayout duplicates day/zone keys from PlannerSemanal/ZonaSemana.
- [x] R8 PlannerLayout::esValida duplicates LayoutPlannerRequest bounds.
- [x] R9 (skipped: not a defect; the panel is always rendered and the queries are bounded by an existing test) hoy._captura-form composer runs 2 queries on every HTMX re-render.

## Progress
- Minor fixes (user request): distinct ColorNota marks via `--color-nota-*` tokens (light unchanged, dark #ecb696/#c26d34/#aebf92/#8c934a/#9a928a, all >=4:1 on card); planner day name ellipsis keeps the number visible. Checks: php 361 passed, npm test 17/17, build OK.
- Review: RDD off globally, so `/code-review high` launched over the full working tree.
- Exploration done; user clarified T2 means moving/resizing the day cards themselves.

## Next step
User decisions on open items; cleanup of test guests.
