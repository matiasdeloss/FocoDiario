# Sistema visual: fuentes y modo oscuro

## Objective
Apply the closed visual spec (`C:\Users\matia\OneDrive\Escritorio\FocoDiario Sistema Visual.html`): new fonts, "Carbón cálido" dark mode, and a manual light/dark toggle in the nav.

## Problem / Why
The app only has a light theme and uses Newsreader + Figtree. The user approved Lora (headings), Source Sans 3 (body/UI) and Figtree only for numbers, plus a warm dark theme.

## Scope
- Fonts: Lora Variable, Source Sans 3 Variable, Figtree Variable kept for numbers (`--font-num`). Remove Newsreader.
- Dark tokens: same token names as `organic.css`, values from the spec, under `@media (prefers-color-scheme: dark) :root:not([data-theme="light"])` and `:root[data-theme="dark"]`, both with `color-scheme: dark`.
- Toggle: pill with two selectable buttons (sun, moon) in the nav, right of the user name and left of "Salir". For guests: right of "Invitado".
- Choice persists in `localStorage`; with no choice, follow the system. Inline head script sets `data-theme` before paint (no flash).
- Links in dark mode use `--color-accent-700` (`#e7a177`), as proposed in the spec.
- Move hardcoded colors outside `organic.css` to variables.
- Activity colors: DB accent hex stays; dark variants for background/text per spec.

## Constraints
- No commits unless the user asks (user rule overrides ODD work-unit commits).
- Light mode values must not change.
- Artifacts (code, comments) follow the project's existing language (Spanish comments).

## Route
- T1 delegated direct (writer trigger: 2+ non-trivial files across CSS, Blade, JS).

## TDD
Off (no project/session TDD configuration). Ordinary checks: `php artisan test`, `npm test`, `npm run build`.

## Tasks
- [x] T1 Fonts, dark tokens, hardcoded colors, activity dark variants, nav toggle with persistence. Evidence: `npm run build` OK, `npm test` 12/12, `php artisan test` 336 passed (writer); `npm test` 12/12 re-run by parent. Not committed (user rule). Assumed: dark colors for calendar types (recordatorio/nota/sesion) not in spec; terracota/oliva note save buttons use dark text in dark mode.
- [x] T2 Visual verification (done in notas-planner-calendario-captura T5) in both modes (Hoy, Agenda, Calendario, Tareas, Recordatorios, Tablero, Estudio, Recomendaciones, Notas, modals, guest toast).

## Acceptance criteria
- Toggle switches theme instantly, persists on reload, no flash.
- Light mode identical to before except fonts.
- Text contrast AA in dark mode, including status colors.

## Progress
- Feature document created.
- T1 done (toggle: `layouts/_tema.blade.php`, `resources/js/tema.js`, `tema-logica.js`; dark tokens at end of `organic.css`).

## Next step
T2: visual pass in both modes, mobile collapsed nav, guest toast, AA contrast of calendar-type colors.
