<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Acceso denegado | JoySys</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            font-family: Arial, sans-serif;
            background: #101010;
            color: #f5f5f5;
        }

        .card {
            width: 100%;
            max-width: 460px;
            padding: 36px;
            background: #191919;
            border: 1px solid #303030;
            border-radius: 16px;
            text-align: center;
        }

        .brand {
            font-size: 28px;
            font-weight: bold;
            letter-spacing: 2px;
            color: #d4b477;
        }

        .error-code {
            margin: 28px 0 12px;
            font-size: 76px;
            font-weight: bold;
            color: #d4b477;
        }

        h1 {
            font-size: 24px;
            margin: 0 0 16px;
        }

        p {
            color: #a3a3a3;
            font-size: 15px;
            line-height: 1.7;
        }

        .actions {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-top: 28px;
        }

        .button {
            display: block;
            width: 100%;
            padding: 13px 18px;
            border: 1px solid #d4b477;
            border-radius: 8px;
            background: #d4b477;
            color: #101010;
            font-size: 14px;
            font-weight: bold;
            text-decoration: none;
            cursor: pointer;
        }

        .button:hover {
            opacity: 0.9;
        }

        .secondary {
            background: transparent;
            border-color: #555;
            color: #f5f5f5;
        }

        form {
            margin: 0;
        }

        .footer {
            margin-top: 28px;
            color: #777;
            font-size: 12px;
        }
    </style>
</head>

<body>

    <div class="card">

        <div class="brand">JoySys</div>

        <div class="error-code">403</div>

        <h1>Acceso denegado</h1>

        <p>
            No tienes los permisos necesarios para acceder
            a esta sección o realizar esta operación.
        </p>

        <p>
            Si consideras que necesitas acceso,
            contacta al administrador del sistema.
        </p>

        <div class="actions">

            @auth

                @can('joysys.permiso', 'dashboard.ver')
                    <a
                        href="{{ route('dashboard') }}"
                        class="button"
                    >
                        Volver al dashboard
                    </a>
                @endcan

                <form
                    method="POST"
                    action="{{ route('logout') }}"
                >
                    @csrf

                    <button
                        type="submit"
                        class="button secondary"
                    >
                        Cerrar sesión
                    </button>
                </form>

            @else

                <a
                    href="{{ route('login') }}"
                    class="button"
                >
                    Iniciar sesión
                </a>

            @endauth

        </div>

        <div class="footer">
            JoySys — Sistema de gestión de joyería
        </div>

    </div>

</body>
</html>