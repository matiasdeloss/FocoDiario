<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Entrar · FocoDiario</title>
    @vite(['resources/css/app.css'])
</head>
<body class="entrada">
    <main class="entrada-tarjeta">
        <p class="entrada-marca"><i class="bi bi-bullseye" aria-hidden="true"></i> FocoDiario</p>
        <h1 class="entrada-titulo">Entrar</h1>
        <p class="entrada-texto">Menos ocio, más foco. Un día a la vez.</p>

        <form method="POST" action="{{ route('login.store') }}" class="entrada-form" novalidate>
            @csrf

            @error('email')
                <div class="aviso-foco aviso-error mb-0" role="alert" id="entrada-error">{{ $message }}</div>
            @enderror

            <div>
                <label for="email" class="form-label">Email</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror"
                       autocomplete="username" required autofocus @error('email') aria-invalid="true" aria-describedby="entrada-error" @enderror>
            </div>

            <div>
                <label for="password" class="form-label">Contraseña</label>
                <input type="password" id="password" name="password" class="form-control @error('password') is-invalid @enderror"
                       autocomplete="current-password" required>
                @error('password')
                    <p class="dialogo-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="form-check">
                <input type="checkbox" id="recordar" name="recordar" value="1" class="form-check-input" @checked(old('recordar', true))>
                <label for="recordar" class="form-check-label">Mantener la sesión abierta en este equipo</label>
            </div>

            <button type="submit" class="btn btn-foco w-100">Entrar</button>
        </form>
    </main>
</body>
</html>
