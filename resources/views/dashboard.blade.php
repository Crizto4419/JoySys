<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Dashboard | JoySys</title>

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
        }

        header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 20px 32px;
            background: #191919;
            border-bottom: 1px solid #303030;
        }

        .brand {
            margin: 0;
            color: #d4b477;
            font-size: 26px;
        }

        button {
            padding: 10px 16px;
            border: 1px solid #555555;
            border-radius: 8px;
            background: #242424;
            color: #ffffff;
            cursor: pointer;
        }

        button:hover {
            background: #333333;
        }

        main {
            max-width: 1100px;
            margin: auto;
            padding: 32px 24px;
        }

        .subtitle {
            color: #a3a3a3;
            line-height: 1.5;
        }

        .grid {
            display: grid;
            grid-template-columns:
                repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-top: 32px;
        }

        .card {
            padding: 24px;
            background: #191919;
            border: 1px solid #303030;
            border-radius: 12px;
        }

        .card-label {
            margin: 0 0 12px;
            color: #a3a3a3;
            font-size: 13px;
        }

        .card-value {
            margin: 0;
            font-size: 19px;
            font-weight: bold;
            overflow-wrap: anywhere;
        }

        .active {
            color: #8dd5a3;
        }

        .notice {
            margin-top: 24px;
            padding: 20px;
            border: 1px solid #51462f;
            border-radius: 10px;
            background: #211e17;
            color: #d4b477;
            line-height: 1.5;
            font-size: 14px;
        }
    </style>
</head>

<body>

    <header>

        <h1 class="brand">JoySys</h1>

        <form
            method="POST"
            action="{{ route('logout') }}"
        >
            @csrf

            <button type="submit">
                Cerrar sesión
            </button>

        </form>

    </header>

    <main>

        <h2>
            Bienvenido, {{ auth()->user()->name }}
        </h2>

        <p class="subtitle">
            Has iniciado sesión correctamente en JoySys.
        </p>

        <section class="grid">

            <article class="card">
                <p class="card-label">
                    Usuario
                </p>

                <p class="card-value">
                    {{ auth()->user()->username }}
                </p>
            </article>

            <article class="card">
                <p class="card-label">
                    Código de empleado
                </p>

                <p class="card-value">
                    {{ auth()->user()->codigo_empleado }}
                </p>
            </article>

            <article class="card">
                <p class="card-label">
                    Rol asignado
                </p>

                <p class="card-value">
                    {{ auth()->user()->role->nombre }}
                </p>
            </article>

            <article class="card">
                <p class="card-label">
                    Estado de la cuenta
                </p>

                <p class="card-value active">
                    Activo
                </p>
            </article>

        </section>

        <div class="notice">
            JoySys MVP 1.0 — Dashboard provisional.
            Los módulos operativos se habilitarán
            conforme avance la implementación.
        </div>

    </main>

</body>
</html>