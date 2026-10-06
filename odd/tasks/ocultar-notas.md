# Ocultar notas de la pantalla Notas

**Objetivo:** poder ocultar notas de la pantalla Notas para despejarla, sin que desaparezcan del resto de la app.

**Decisión del usuario (2026-10-05):** una nota oculta deja de verse solo en la pantalla Notas; en la vista de contextos (y en el resto de la app) sigue apareciendo.

**Propuesta de comportamiento (por defecto, ajustable):**
- Acción "Ocultar" en cada nota de la pantalla Notas, con toast de confirmación y "Deshacer".
- Un filtro o enlace "Ver ocultas (N)" en la pantalla Notas para encontrarlas y la acción "Mostrar" para devolverlas.
- Se guarda en la nota (por ejemplo `notas.oculta_en_notas` booleano), no se borra nada.

**Restricciones:** rama `develop`; sin commits ni push hasta que el usuario lo pida. Va después de `tableros-y-notas-vinculadas` en la cola, salvo que el usuario la priorice.

**TDD:** off. Chequeos: `php artisan test`, `npm test`, `npm run build`.

## Tareas

- [x] O1 — Columna y migración, acción ocultar/mostrar con toast y Deshacer — ruta: delegada (migración `2026_10_14_000001`, PATCH `notas.ocultar`/`notas.mostrar` con JSON, aviso con Deshacer en notas.js; `php artisan test` 473 ok)
- [x] O2 — Filtro "Ver ocultas" en Notas; la vista de contextos y el resto de la app no cambian — ruta: delegada (`?ocultas=1`, pastilla con conteo; solo NotaController::index filtra por `oculta`)
  - Decisión (2026-10-06): con un contexto elegido, bajo la lista va "y N ocultas · ver" (enlace a `contexto=X&ocultas=1`, conserva q/color/fijadas); el conteo respeta esos mismos filtros; no aparece con 0, sin contexto ni viendo ocultas; notas.js lo actualiza en vivo.
- [x] O3 — Tests — ruta: delegada (`tests/Feature/NotasOcultasTest.php`, 14 tests; `npm test` 26 ok, `npm run build` ok). Revisión visual en navegador pendiente.

## Progreso

- 2026-10-05: documento creado.
- 2026-10-06: O1–O3 implementadas (decisión: oculta = boolean `notas.oculta`; Deshacer y avisos del lado del cliente). Pendiente: revisión visual luz/oscuro/móvil.
