<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Iniciar sesión | JoySys</title>

    <style>
        :root {
            color-scheme: dark;
            font-family: Arial, Helvetica, sans-serif;
            background: #101010;
            color: #f5f5f5;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
        }

        .login-card {
            width: 100%;
            max-width: 420px;
            padding: 36px;
            background: #191919;
            border: 1px solid #303030;
            border-radius: 16px;
        }

        .brand {
            margin: 0;
            color: #d4b477;
            font-size: 36px;
            text-align: center;
            letter-spacing: 2px;
        }

        .subtitle {
            margin: 8px 0 32px;
            color: #a3a3a3;
            font-size: 14px;
            text-align: center;
        }

        h2 {
            margin: 0 0 24px;
            font-size: 22px;
        }

        .field {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
        }

        input {
            width: 100%;
            padding: 13px 14px;
            border: 1px solid #414141;
            border-radius: 8px;
            background: #111111;
            color: #ffffff;
            font-size: 15px;
        }

        input:focus {
            outline: 2px solid #d4b477;
            outline-offset: 2px;
        }

        button {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 8px;
            background: #d4b477;
            color: #141414;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
        }

        button:hover {
            background: #e1c48d;
        }

        .error {
            margin-bottom: 20px;
            padding: 12px;
            background: #351b1b;
            border: 1px solid #713535;
            border-radius: 8px;
            color: #ffb4b4;
            font-size: 13px;
            line-height: 1.5;
        }

        .error p {
            margin: 4px 0;
        }

        .status {
            margin-bottom: 20px;
            padding: 12px;
            background: #183022;
            border: 1px solid #305c40;
            border-radius: 8px;
            color: #b6e7c5;
            font-size: 13px;
        }

        .footer {
            margin: 24px 0 0;
            color: #a3a3a3;
            text-align: center;
            font-size: 12px;
        }
    </style>
</head>

<body>

    <main class="login-card">

        <h1 class="brand">JoySys</h1>

        <p class="subtitle">
            Sistema de gestión de joyería
        </p>

        <h2>Iniciar sesión</h2>

        @if (session('status'))
            <div class="status" role="status">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="error" role="alert">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form
            method="POST"
            action="{{ route('login.store') }}"
        >
            @csrf

            <div class="field">
                <label for="username">
                    Usuario
                </label>

                <input
                    type="text"
                    id="username"
                    name="username"
                    value="{{ old('username') }}"
                    maxlength="50"
                    autocomplete="username"
                    placeholder="Nombre de usuario"
                    required
                    autofocus
                >
            </div>

            <div class="field">
                <label for="password">
                    Contraseña
                </label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    maxlength="72"
                    autocomplete="current-password"
                    placeholder="Introduce tu contraseña"
                    required
                >
            </div>

            <button type="submit">
                Iniciar sesión
            </button>

        </form>

        <p class="footer">
            Acceso exclusivo para personal autorizado
        </p>

    </main>

</body>
</html>