<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard | JoySys</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #101010;
            color: #f5f5f5;
        }

        header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px 32px;
            background: #191919;
            border-bottom: 1px solid #303030;
        }

        .brand {
            color: #d4b477;
            font-size: 28px;
            font-weight: bold;
            letter-spacing: 2px;
        }

        .logout {
            padding: 10px 18px;
            border: 1px solid #555;
            border-radius: 8px;
            background: transparent;
            color: #f5f5f5;
            cursor: pointer;
        }

        .logout:hover {
            background: #303030;
        }

        main {
            max-width: 1200px;
            margin: auto;
            padding: 32px;
        }

        h1 {
            margin-bottom: 8px;
            font-size: 28px;
        }

        h2 {
            margin-top: 36px;
            font-size: 21px;
        }

        .subtitle {
            color: #a3a3a3;
            line-height: 1.6;
        }

        .grid {
            display: grid;
            grid-template-columns:
                repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-top: 24px;
        }

        .card {
            background: #191919;
            border: 1px solid #303030;
            border-radius: 12px;
            padding: 22px;
        }

        .label {
            color: #a3a3a3;
            font-size: 13px;
            margin-bottom: 10px;
        }

        .value {
            font-size: 19px;
            font-weight: bold;
        }

        .active {
            color: #86efac;
        }

        .module-title {
            color: #d4b477;
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .module-description {
            color: #a3a3a3;
            font-size: 14px;
            line-height: 1.6;
        }

        .module-status {
            display: inline-block;
            margin-top: 16px;
            padding: 6px 10px;
            background: #252525;
            border: 1px solid #404040;
            border-radius: 6px;
            font-size: 12px;
            color: #bdbdbd;
        }

        .notice {
            margin-top: 32px;
            padding: 18px;
            background: #191919;
            border: 1px solid #303030;
            border-left: 3px solid #d4b477;
            border-radius: 8px;
            color: #bdbdbd;
            line-height: 1.6;
        }

        @media (max-width: 600px) {
            header {
                padding: 16px;
            }

            main {
                padding: 20px;
            }

            h1 {
                font-size: 24px;
            }
        }
    </style>
</head>

<body>

    <header>
        <div class="brand">JoySys</div>

        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <button type="submit" class="logout">
                Cerrar sesión
            </button>
        </form>
    </header>

    <main>
        <h1>Bienvenido, {{ auth()->user()->name }}</h1>

        <p class="subtitle">
            Has iniciado sesión correctamente en JoySys.
        </p>

        <div class="grid">

            <div class="card">
                <div class="label">Usuario</div>

                <div class="value">
                    {{ auth()->user()->username }}
                </div>
            </div>

            <div class="card">
                <div class="label">Código de empleado</div>

                <div class="value">
                    {{ auth()->user()->codigo_empleado }}
                </div>
            </div>

            <div class="card">
                <div class="label">Rol asignado</div>

                <div class="value">
                    {{ auth()->user()->role->nombre }}
                </div>
            </div>

            <div class="card">
                <div class="label">Estado de la cuenta</div>

                <div class="value active">
                    Activo
                </div>
            </div>

        </div>

        <h2>Módulos autorizados</h2>

        <p class="subtitle">
            Los módulos visibles corresponden a los permisos
            asignados a tu rol.
        </p>

        @php
            $modulos = [
                [
                    'permiso' => 'catalogos.ver',
                    'nombre' => 'Catálogos',
                    'descripcion' =>
                        'Consulta de productos y catálogos generales.',
                ],
                [
                    'permiso' => 'inventario.ver',
                    'nombre' => 'Inventario',
                    'descripcion' =>
                        'Consulta de piezas y existencias.',
                ],
                [
                    'permiso' => 'incidencias.ver',
                    'nombre' => 'Incidencias',
                    'descripcion' =>
                        'Consulta de incidencias de inventario.',
                ],
                [
                    'permiso' => 'precios.ver',
                    'nombre' => 'Precios',
                    'descripcion' =>
                        'Consulta de precios de productos.',
                ],
                [
                    'permiso' => 'clientes.ver',
                    'nombre' => 'Clientes',
                    'descripcion' =>
                        'Consulta de información de clientes.',
                ],
                [
                    'permiso' => 'ventas.ver',
                    'nombre' => 'Ventas',
                    'descripcion' =>
                        'Consulta de operaciones comerciales.',
                ],
                [
                    'permiso' => 'pagos.ver',
                    'nombre' => 'Pagos',
                    'descripcion' =>
                        'Consulta de pagos registrados.',
                ],
                [
                    'permiso' => 'caja.ver',
                    'nombre' => 'Caja',
                    'descripcion' =>
                        'Consulta de operaciones de caja.',
                ],
                [
                    'permiso' => 'reportes.ver',
                    'nombre' => 'Reportes',
                    'descripcion' =>
                        'Consulta de reportes operativos.',
                ],
                [
                    'permiso' => 'sucursales.ver',
                    'nombre' => 'Sucursales',
                    'descripcion' =>
                        'Consulta de sucursales registradas.',
                ],
                [
                    'permiso' => 'usuarios.ver',
                    'nombre' => 'Usuarios',
                    'descripcion' =>
                        'Consulta de usuarios del sistema.',
                ],
                [
                    'permiso' => 'roles.ver',
                    'nombre' => 'Roles y permisos',
                    'descripcion' =>
                        'Consulta de roles y autorizaciones.',
                ],
                [
                    'permiso' => 'configuracion.gestionar',
                    'nombre' => 'Configuración',
                    'descripcion' =>
                        'Administración de configuraciones.',
                ],
            ];

            $modulosVisibles = 0;
        @endphp

        <div class="grid">

            @foreach ($modulos as $modulo)

                @can('joysys.permiso', $modulo['permiso'])

                    @php
                        $modulosVisibles++;
                    @endphp

                    <div class="card">
                        <div class="module-title">
                            {{ $modulo['nombre'] }}
                        </div>

                        <div class="module-description">
                            {{ $modulo['descripcion'] }}
                        </div>

                        <span class="module-status">
                            Próximamente
                        </span>
                    </div>

                @endcan

            @endforeach

        </div>

        @if ($modulosVisibles === 0)
            <div class="notice">
                No tienes módulos adicionales autorizados.
                Contacta al administrador si necesitas acceso.
            </div>
        @endif

        <div class="notice">
            JoySys MVP 1.0 — Dashboard provisional.
            Los módulos operativos se habilitarán conforme
            avance la implementación del sistema.
        </div>

    </main>

</body>
</html>