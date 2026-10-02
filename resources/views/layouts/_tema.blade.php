{{-- Interruptor de tema: claro / oscuro. El estado (aria-pressed) y la elección los maneja resources/js/tema.js. --}}
<div class="tema-interruptor" role="group" aria-label="Tema">
    <button type="button" class="tema-opcion" data-tema-opcion="light" aria-pressed="true">
        <i class="bi bi-sun" aria-hidden="true"></i><span class="visually-hidden">Claro</span>
    </button>
    <button type="button" class="tema-opcion" data-tema-opcion="dark" aria-pressed="false">
        <i class="bi bi-moon" aria-hidden="true"></i><span class="visually-hidden">Oscuro</span>
    </button>
</div>
