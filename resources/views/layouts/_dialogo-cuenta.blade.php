{{--
    Modal de cuenta, solo para invitados: iniciar sesión o crear cuenta. No hay pantalla de entrada aparte.
    Lo maneja resources/js/cuenta.js (envía JSON); sin JS, los formularios se envían como siempre.
    Se abre desde la barra ([data-abrir-cuenta]) o con ?cuenta=entrar / ?cuenta=crear.
--}}
<dialog id="dialogo-cuenta" class="dialogo dialogo-cuenta" aria-labelledby="cuenta-marca"
        @if (in_array(request()->query('cuenta'), ['entrar', 'crear'], true)) data-abrir-al-cargar="{{ request()->query('cuenta') }}" @endif>
    <div class="dialogo-cuerpo">
        <button type="button" class="dialogo-cerrar cuenta-cerrar" data-cerrar-cuenta aria-label="Cerrar"><i class="bi bi-x-lg" aria-hidden="true"></i></button>

        <div>
            <p class="entrada-marca" id="cuenta-marca"><i class="bi bi-bullseye" aria-hidden="true"></i> FocoDiario</p>
            <p class="entrada-texto">Tu gestión diaria personal</p>
        </div>

        <div class="cuenta-pestanas" role="tablist" aria-label="Cuenta">
            <button type="button" role="tab" id="cuenta-tab-entrar" aria-controls="cuenta-panel-entrar" aria-selected="true" data-pestana="entrar">Iniciar sesión</button>
            <button type="button" role="tab" id="cuenta-tab-crear" aria-controls="cuenta-panel-crear" aria-selected="false" tabindex="-1" data-pestana="crear">Crear cuenta</button>
        </div>

        {{-- Iniciar sesión --}}
        <form id="cuenta-panel-entrar" role="tabpanel" aria-labelledby="cuenta-tab-entrar" class="entrada-form"
              method="POST" action="{{ route('login.store') }}" novalidate data-form-cuenta>
            @csrf
            <input type="hidden" name="recordar" value="1">
            <p class="cuenta-nota"><i class="bi bi-arrow-left-right" aria-hidden="true"></i> Lo que hiciste como invitado se suma a tu cuenta.</p>
            <div class="dialogo-avisos" data-avisos role="alert"></div>
            <div>
                <label for="cuenta-entrar-email" class="form-label">Email</label>
                <div class="cuenta-campo">
                    <i class="bi bi-envelope" aria-hidden="true"></i>
                    <input type="email" id="cuenta-entrar-email" name="email" class="form-control" autocomplete="username" required aria-describedby="cuenta-entrar-error-email">
                </div>
                <p class="dialogo-error" id="cuenta-entrar-error-email" data-error="email"></p>
            </div>
            <div>
                <label for="cuenta-entrar-password" class="form-label">Contraseña</label>
                <div class="cuenta-campo cuenta-campo-clave">
                    <i class="bi bi-lock" aria-hidden="true"></i>
                    <input type="password" id="cuenta-entrar-password" name="password" class="form-control" autocomplete="current-password" required aria-describedby="cuenta-entrar-error-password">
                    <button type="button" class="cuenta-ver" data-ver-clave aria-label="Mostrar contraseña" aria-pressed="false"><i class="bi bi-eye" aria-hidden="true"></i></button>
                </div>
                <p class="dialogo-error" id="cuenta-entrar-error-password" data-error="password"></p>
            </div>
            <div class="cuenta-pie">
                <button type="button" class="cuenta-cambiar" data-cambiar-pestana="crear">¿No tenés cuenta? <strong>Crear cuenta</strong></button>
                <button type="submit" class="btn btn-foco cuenta-enviar" data-enviar>Iniciar sesión <i class="bi bi-arrow-right" aria-hidden="true"></i></button>
            </div>
        </form>

        {{-- Crear cuenta --}}
        <form id="cuenta-panel-crear" role="tabpanel" aria-labelledby="cuenta-tab-crear" class="entrada-form"
              method="POST" action="{{ route('cuenta.store') }}" novalidate data-form-cuenta hidden>
            @csrf
            <p class="cuenta-nota"><i class="bi bi-shield-check" aria-hidden="true"></i> Tus datos están guardados solo en este navegador. Creá una cuenta para no perderlos y usarlos en otros equipos.</p>
            <div class="dialogo-avisos" data-avisos role="alert"></div>
            <div>
                <label for="cuenta-crear-nombre" class="form-label">Nombre <span class="dialogo-opcional">(opcional)</span></label>
                <div class="cuenta-campo">
                    <i class="bi bi-person" aria-hidden="true"></i>
                    <input type="text" id="cuenta-crear-nombre" name="nombre" class="form-control" autocomplete="name" maxlength="80" aria-describedby="cuenta-crear-error-nombre">
                </div>
                <p class="dialogo-error" id="cuenta-crear-error-nombre" data-error="nombre"></p>
            </div>
            <div>
                <label for="cuenta-crear-email" class="form-label">Email</label>
                <div class="cuenta-campo">
                    <i class="bi bi-envelope" aria-hidden="true"></i>
                    <input type="email" id="cuenta-crear-email" name="email" class="form-control" autocomplete="email" required aria-describedby="cuenta-crear-error-email">
                </div>
                <p class="dialogo-error" id="cuenta-crear-error-email" data-error="email"></p>
            </div>
            <div>
                <label for="cuenta-crear-password" class="form-label">Contraseña <span class="dialogo-opcional">(mínimo 8 caracteres)</span></label>
                <div class="cuenta-campo cuenta-campo-clave">
                    <i class="bi bi-lock" aria-hidden="true"></i>
                    <input type="password" id="cuenta-crear-password" name="password" class="form-control" autocomplete="new-password" minlength="8" required aria-describedby="cuenta-crear-error-password">
                    <button type="button" class="cuenta-ver" data-ver-clave aria-label="Mostrar contraseña" aria-pressed="false"><i class="bi bi-eye" aria-hidden="true"></i></button>
                </div>
                <p class="dialogo-error" id="cuenta-crear-error-password" data-error="password"></p>
            </div>
            <div>
                <label for="cuenta-crear-password2" class="form-label">Repetí la contraseña</label>
                <div class="cuenta-campo cuenta-campo-clave">
                    <i class="bi bi-lock" aria-hidden="true"></i>
                    <input type="password" id="cuenta-crear-password2" name="password_confirmation" class="form-control" autocomplete="new-password" required>
                    <button type="button" class="cuenta-ver" data-ver-clave aria-label="Mostrar contraseña" aria-pressed="false"><i class="bi bi-eye" aria-hidden="true"></i></button>
                </div>
            </div>
            <div class="cuenta-pie">
                <button type="button" class="cuenta-cambiar" data-cambiar-pestana="entrar">¿Ya tenés cuenta? <strong>Iniciar sesión</strong></button>
                <button type="submit" class="btn btn-foco cuenta-enviar" data-enviar>Crear cuenta <i class="bi bi-arrow-right" aria-hidden="true"></i></button>
            </div>
        </form>
    </div>
</dialog>
