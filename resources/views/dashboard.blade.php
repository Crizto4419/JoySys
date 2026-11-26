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
            font-family: Arial, Helvetica, sans-serif;
            background: #101010;
            color: #f5f5f5;
        }

        .header {
            background: #191919;
            border-bottom: 1px solid #303030;
            padding: 20px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .brand {
            color: #d4b477;
            font-size: 28px;
            font-weight: bold;
            letter-spacing: 2px;
        }

        .logout-button {
            background: transparent;
            border: 1px solid #d4b477;
            color: #d4b477;
            padding: 10px 18px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
        }

        .logout-button:hover {
            background: #d4b477;
            color: #101010;
        }

        .container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 32px;
        }

        h1 {
            margin: 0 0 10px;
            font-size: 30px;
        }

        h2 {
            margin: 0 0 10px;
            font-size: 22px;
        }

        .subtitle {
            color: #a3a3a3;
            margin: 0 0 24px;
            line-height: 1.6;
        }

        .section {
            margin-bottom: 36px;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(
                auto-fit,
                minmax(230px, 1fr)
            );
            gap: 16px;
        }

        .card {
            background: #191919;
            border: 1px solid #303030;
            border-radius: 12px;
            padding: 22px;
        }

        .card-label {
            font-size: 13px;
            color: #a3a3a3;
            margin-bottom: 10px;
        }

        .card-value {
            font-size: 18px;
            font-weight: bold;
            overflow-wrap: anywhere;
        }

        .status-active {
            color: #86efac;
        }

        .status-warning {
            color: #fbbf24;
        }

        .notice {
            background: #191919;
            border: 1px solid #303030;
            border-radius: 10px;
            padding: 18px;
            color: #a3a3a3;
            font-size: 14px;
            line-height: 1.6;
        }

        .notice-warning {
            background: #292214;
            border: 1px solid #78602e;
            color: #f5d58a;
            margin-top: 20px;
        }

        .notice-title {
            font-weight: bold;
            margin-bottom: 8px;
        }

        .module-title {
            font-size: 18px;
            font-weight: bold;
            color: #d4b477;
            margin-bottom: 12px;
        }

        .module-description {
            font-size: 14px;
            color: #a3a3a3;
            line-height: 1.6;
            margin-bottom: 18px;
            min-height: 45px;
        }

        .module-status {
            display: inline-block;
            background: #252525;
            color: #b5b5b5;
            border-radius: 6px;
            padding: 6px 10px;
            font-size: 12px;
        }

        .module-status-restricted {
            background: #332816;
            color: #f5d58a;
        }

        @media (max-width: 600px) {
            .header {
                padding: 16px;
            }

            .container {
                padding: 20px 16px;
            }

            .grid {
                grid-template-columns: 1fr;
            }

            h1 {
                font-size: 24px;
            }

            .brand {
                font-size: 24px;
            }
        }
    </style>
</head>

<body>
    <header class="header">
        <div class="brand">JoySys</div>

        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <button type="submit" class="logout-button">
                Cerrar sesión
            </button>
        </form>
    </header>

    <main class="container">
        <section class="section">
            <h1>Bienvenido, {{ auth()->user()->name }}</h1>

            <p class="subtitle">
                Has iniciado sesión correctamente en JoySys.
            </p>

            <div class="grid">
                <div class="card">
                    <div class="card-label">Usuario</div>

                    <div class="card-value">
                        {{ auth()->user()->username }}
                    </div>
                </div>

                <div class="card">
                    <div class="card-label">
                        Código de empleado
                    </div>

                    <div class="card-value">
                        {{ auth()->user()->codigo_empleado }}
                    </div>
                </div>

                <div class="card">
                    <div class="card-label">Rol asignado</div>

                    <div class="card-value">
                        {{ auth()->user()->role->nombre }}
                    </div>
                </div>

                <div class="card">
                    <div class="card-label">
                        Estado de la cuenta
                    </div>

                    <div class="card-value status-active">
                        Activo
                    </div>
                </div>

                <div class="card">
                    <div class="card-label">
                        Sucursal operativa
                    </div>

                    @if ($sucursalOperativa !== null)
                        <div class="card-value">
                            {{ $sucursalOperativa->nombre }}
                        </div>
                    @else
                        <div class="card-value status-warning">
                            Sin sucursal operativa
                        </div>
                    @endif
                </div>

                <div class="card">
                    <div class="card-label">
                        Código de sucursal
                    </div>

                    <div class="card-value">
                        {{ $sucursalOperativa?->codigo ?? 'No asignado' }}
                    </div>
                </div>
            </div>

            @if ($sucursalOperativa === null)
                <div class="notice notice-warning">
                    <div class="notice-title">
                        Operaciones restringidas
                    </div>

                    No tienes una sucursal operativa habilitada.
                    Contacta al administrador para revisar
                    tu asignación.
                </div>
            @endif
        </section>

        <section class="section">
            <h2>Módulos autorizados</h2>

            <p class="subtitle">
                Los módulos visibles corresponden a los
                permisos asignados a tu rol.
            </p>

            @php
                $modulos = [
                    [
                        'nombre' => 'Catálogos',
                        'permiso' => 'catalogos.ver',
                        'descripcion' => 'Consulta y gestión de catálogos generales.',
                        'requiere_sucursal' => false,
                    ],
                    [
                        'nombre' => 'Inventario',
                        'permiso' => 'inventario.ver',
                        'descripcion' => 'Control de productos, piezas y existencias.',
                        'requiere_sucursal' => true,
                    ],
                    [
                        'nombre' => 'Incidencias',
                        'permiso' => 'incidencias.ver',
                        'descripcion' => 'Registro y seguimiento de incidencias de inventario.',
                        'requiere_sucursal' => true,
                    ],
                    [
                        'nombre' => 'Precios',
                        'permiso' => 'precios.ver',
                        'descripcion' => 'Consulta y administración de precios de productos.',
                        'requiere_sucursal' => false,
                    ],
                    [
                        'nombre' => 'Clientes',
                        'permiso' => 'clientes.ver',
                        'descripcion' => 'Registro y consulta de clientes.',
                        'requiere_sucursal' => false,
                    ],
                    [
                        'nombre' => 'Ventas',
                        'permiso' => 'ventas.ver',
                        'descripcion' => 'Registro y consulta de operaciones de venta.',
                        'requiere_sucursal' => true,
                    ],
                    [
                        'nombre' => 'Pagos',
                        'permiso' => 'pagos.ver',
                        'descripcion' => 'Registro y seguimiento de pagos.',
                        'requiere_sucursal' => true,
                    ],
                    [
                        'nombre' => 'Caja',
                        'permiso' => 'caja.ver',
                        'descripcion' => 'Control de sesiones y movimientos de caja.',
                        'requiere_sucursal' => true,
                    ],
                    [
                        'nombre' => 'Reportes',
                        'permiso' => 'reportes.ver',
                        'descripcion' => 'Consulta de información y resultados operativos.',
                        'requiere_sucursal' => true,
                    ],
                    [
                        'nombre' => 'Sucursales',
                        'permiso' => 'sucursales.ver',
                        'descripcion' => 'Consulta y administración de sucursales.',
                        'requiere_sucursal' => false,
                    ],
                    [
                        'nombre' => 'Usuarios',
                        'permiso' => 'usuarios.ver',
                        'descripcion' => 'Administración de usuarios del sistema.',
                        'requiere_sucursal' => false,
                    ],
                    [
                        'nombre' => 'Roles y permisos',
                        'permiso' => 'roles.ver',
                        'descripcion' => 'Administración de roles y autorizaciones.',
                        'requiere_sucursal' => false,
                    ],
                    [
                        'nombre' => 'Configuración',
                        'permiso' => 'configuracion.gestionar',
                        'descripcion' => 'Administración de parámetros del sistema.',
                        'requiere_sucursal' => false,
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

                            @if (
                                $modulo['requiere_sucursal']
                                && $sucursalOperativa === null
                            )
                                <span class="module-status module-status-restricted">
                                    Sucursal requerida
                                </span>
                            @else
                                <span class="module-status">
                                    Próximamente
                                </span>
                            @endif
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
        </section>

        <div class="notice">
            JoySys MVP 1.0 — Dashboard provisional.
            Los módulos operativos se habilitarán conforme
            avance la implementación del sistema.
        </div>
    </main>
</body>
</html>