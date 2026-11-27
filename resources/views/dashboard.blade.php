@extends('layouts.app')

@section('title', 'Dashboard | JoySys')

@section('content')

    @php
        $usuario = auth()->user();

        $modulos = collect(config('joysys.navegacion', []))
            ->filter(
                fn ($modulo) => $usuario->can(
                    'joysys.permiso',
                    $modulo['permiso']
                )
            );

        $formatoMoneda = static fn ($valor) =>
            '$' . number_format(
                (float) $valor,
                (int) config('dashboard.decimales_monetarios', 2),
                '.',
                ','
            );

        $formatoCantidad = static fn ($valor) =>
            rtrim(
                rtrim(
                    number_format((float) $valor, 4, '.', ','),
                    '0'
                ),
                '.'
            );

        $formatoFecha = static fn ($fecha) =>
            $fecha
                ? \Carbon\CarbonImmutable::parse($fecha, 'UTC')
                    ->setTimezone(
                        config(
                            'dashboard.zona_horaria',
                            'America/Mexico_City'
                        )
                    )
                    ->format('d/m/Y H:i')
                : 'Sin fecha';
    @endphp

    <div class="js-stack">

        {{-- Encabezado del dashboard --}}

        <section class="flex flex-wrap items-center justify-between gap-4">

            <div>
                <h1 class="js-page-title">
                    Bienvenido, {{ $usuario->name }}
                </h1>

                <p class="js-subtitle">
                    Has iniciado sesión correctamente en JoySys.
                </p>

                <p class="js-text-small mt-1">
                    Resumen operativo de tu sucursal.
                </p>
            </div>

            <div class="js-card flex items-center gap-3">

                <x-layout.icon name="clock" />

                <div>
                    <p class="js-text-small">
                        Fecha operativa
                    </p>

                    <p class="font-semibold">
                        {{ $resumen['fecha_operativa'] }}
                    </p>
                </div>

            </div>

        </section>

        {{-- Restricción de sucursal --}}

        @if ($sucursalOperativa === null)

            <div class="js-alert js-alert-warning">

                <x-layout.icon name="lock" />

                <div>

                    <p class="font-semibold">
                        Operaciones restringidas
                    </p>

                    <p>
                        No tienes una sucursal operativa habilitada.
                        Contacta con el administrador para continuar
                        con las operaciones que requieren sucursal.
                    </p>

                </div>

            </div>

        @endif

        {{-- Indicadores comerciales --}}

        @if (
            $resumen['ventas'] !== null ||
            $resumen['cobros'] !== null
        )

            <section class="js-stack">

                <div>
                    <h2 class="js-section-title">
                        Resumen comercial
                    </h2>

                    <p class="js-subtitle">
                        Operaciones registradas durante el día.
                    </p>
                </div>

                <div class="js-grid js-grid-3">

                    @if ($resumen['ventas'] !== null)

                        <article class="js-card">

                            <div class="flex items-center justify-between gap-3">

                                <span class="js-text-muted">
                                    Ventas del día
                                </span>

                                <span class="js-text-primary">
                                    <x-layout.icon name="cart" />
                                </span>

                            </div>

                            <p class="js-page-title js-text-numeric mt-3">
                                {{ number_format($resumen['ventas']['cantidad']) }}
                            </p>

                            <p class="js-text-small mt-1">
                                Ventas completadas
                            </p>

                            <span class="js-badge js-badge-primary mt-3">
                                {{ $resumen['ventas']['alcance'] === 'sucursal'
                                    ? 'Sucursal'
                                    : 'Mis operaciones' }}
                            </span>

                        </article>

                        <article class="js-card">

                            <div class="flex items-center justify-between gap-3">

                                <span class="js-text-muted">
                                    Importe vendido
                                </span>

                                <span class="js-text-primary">
                                    <x-layout.icon name="tag" />
                                </span>

                            </div>

                            <p class="js-page-title js-text-numeric mt-3 break-words">
                                {{ $formatoMoneda($resumen['ventas']['importe']) }}
                            </p>

                            <p class="js-text-small mt-1">
                                MXN · Ventas completadas
                            </p>

                            <span class="js-badge js-badge-primary mt-3">
                                {{ $resumen['ventas']['alcance'] === 'sucursal'
                                    ? 'Sucursal'
                                    : 'Mis operaciones' }}
                            </span>

                        </article>

                    @endif

                    @if ($resumen['cobros'] !== null)

                        <article class="js-card">

                            <div class="flex items-center justify-between gap-3">

                                <span class="js-text-muted">
                                    Cobros del día
                                </span>

                                <span class="js-text-primary">
                                    <x-layout.icon name="credit-card" />
                                </span>

                            </div>

                            <p class="js-page-title js-text-numeric mt-3 break-words">
                                {{ $formatoMoneda($resumen['cobros']['importe']) }}
                            </p>

                            <p class="js-text-small mt-1">
                                MXN · Cobros brutos confirmados
                            </p>

                            <span class="js-badge js-badge-primary mt-3">
                                {{ $resumen['cobros']['alcance'] === 'sucursal'
                                    ? 'Sucursal'
                                    : 'Mis operaciones' }}
                            </span>

                        </article>

                    @endif

                </div>

            </section>

        @endif

        {{-- Inventario disponible --}}

        @if ($resumen['inventario'] !== null)

            <section class="js-stack">

                <div>
                    <h2 class="js-section-title">
                        Inventario disponible
                    </h2>

                    <p class="js-subtitle">
                        Existencias disponibles de productos activos.
                    </p>
                </div>

                <div class="js-grid js-grid-3">

                    <article class="js-card">

                        <div class="flex items-center justify-between gap-3">

                            <span class="js-text-muted">
                                Piezas individuales
                            </span>

                            <span class="js-text-primary">
                                <x-layout.icon name="gem" />
                            </span>

                        </div>

                        <p class="js-page-title js-text-numeric mt-3">
                            {{ number_format($resumen['inventario']['piezas']) }}
                        </p>

                        <p class="js-text-small mt-1">
                            Piezas disponibles
                        </p>

                    </article>

                    <article class="js-card">

                        <div class="flex items-center justify-between gap-3">

                            <span class="js-text-muted">
                                Productos por cantidad
                            </span>

                            <span class="js-text-primary">
                                <x-layout.icon name="boxes" />
                            </span>

                        </div>

                        <p class="js-page-title js-text-numeric mt-3">
                            {{ $formatoCantidad($resumen['inventario']['unidades']) }}
                        </p>

                        <p class="js-text-small mt-1">
                            Unidades disponibles
                        </p>

                    </article>

                    <article class="js-card">

                        <div class="flex items-center justify-between gap-3">

                            <span class="js-text-muted">
                                Productos por peso
                            </span>

                            <span class="js-text-primary">
                                <x-layout.icon name="package" />
                            </span>

                        </div>

                        <p class="js-page-title js-text-numeric mt-3">
                            {{ $formatoCantidad($resumen['inventario']['gramos']) }} g
                        </p>

                        <p class="js-text-small mt-1">
                            Gramos disponibles
                        </p>

                    </article>

                </div>

            </section>

        @endif

        {{-- Caja e incidencias --}}

        @if (
            $resumen['caja'] !== null ||
            $resumen['incidencias'] !== null
        )

            <section class="js-stack">

                <div>
                    <h2 class="js-section-title">
                        Estado operativo
                    </h2>

                    <p class="js-subtitle">
                        Información actual de la sucursal.
                    </p>
                </div>

                <div class="js-grid js-grid-2">

                    @if ($resumen['caja'] !== null)

                        <article class="js-card js-stack">

                            <div class="flex items-center justify-between gap-3">

                                <h3 class="js-card-title">
                                    Estado de caja
                                </h3>

                                <span class="js-text-primary">
                                    <x-layout.icon name="wallet" />
                                </span>

                            </div>

                            <div class="flex items-center justify-between">

                                <span class="js-text-muted">
                                    Cajas activas
                                </span>

                                <span class="font-semibold js-text-numeric">
                                    {{ $resumen['caja']['cajas_activas'] }}
                                </span>

                            </div>

                            @if ($resumen['caja']['sesiones_abiertas'] !== null)

                                <div class="flex items-center justify-between">

                                    <span class="js-text-muted">
                                        Sesiones abiertas
                                    </span>

                                    <span class="font-semibold js-text-numeric">
                                        {{ $resumen['caja']['sesiones_abiertas'] }}
                                    </span>

                                </div>

                            @endif

                            <div class="js-divider"></div>

                            @if ($resumen['caja']['sesion_propia'] !== null)

                                <span class="js-badge js-badge-success self-start">
                                    Sesión propia abierta
                                </span>

                                <div>

                                    <p class="font-semibold">
                                        {{ $resumen['caja']['sesion_propia']->nombre }}
                                    </p>

                                    <p class="js-text-small">
                                        {{ $resumen['caja']['sesion_propia']->codigo }}
                                    </p>

                                    <p class="js-text-small mt-2">
                                        Apertura:
                                        {{ $formatoFecha(
                                            $resumen['caja']['sesion_propia']->fecha_apertura
                                        ) }}
                                    </p>

                                    @if (
                                        $resumen['caja']['sesion_propia']->estado_caja
                                        !== 'activa'
                                    )

                                        <p class="js-text-warning mt-2">
                                            La caja no está activa.
                                        </p>

                                    @endif

                                </div>

                            @else

                                <span class="js-badge self-start">
                                    Sin sesión propia abierta
                                </span>

                                <p class="js-text-small">
                                    Actualmente no tienes una sesión
                                    de caja abierta en esta sucursal.
                                </p>

                            @endif

                        </article>

                    @endif

                    @if ($resumen['incidencias'] !== null)

                        <article class="js-card js-stack">

                            <div class="flex items-center justify-between gap-3">

                                <h3 class="js-card-title">
                                    Incidencias pendientes
                                </h3>

                                <span class="js-text-primary">
                                    <x-layout.icon name="alert" />
                                </span>

                            </div>

                            <p class="js-page-title js-text-numeric">
                                {{ number_format($resumen['incidencias']['total']) }}
                            </p>

                            <div class="js-divider"></div>

                            <div class="flex items-center justify-between">

                                <span class="js-text-muted">
                                    Abiertas
                                </span>

                                <span class="font-semibold">
                                    {{ $resumen['incidencias']['abiertas'] }}
                                </span>

                            </div>

                            <div class="flex items-center justify-between">

                                <span class="js-text-muted">
                                    En revisión
                                </span>

                                <span class="font-semibold">
                                    {{ $resumen['incidencias']['en_revision'] }}
                                </span>

                            </div>

                            @if ($resumen['incidencias']['total'] === 0)

                                <span class="js-badge js-badge-success self-start">
                                    Sin incidencias pendientes
                                </span>

                            @else

                                <span class="js-badge js-badge-warning self-start">
                                    Requiere atención
                                </span>

                            @endif

                        </article>

                    @endif

                </div>

            </section>

        @endif

        {{-- Accesos rápidos --}}

        @if (count($resumen['accesos_rapidos']) > 0)

            <section class="js-stack">

                <div>
                    <h2 class="js-section-title">
                        Accesos rápidos
                    </h2>

                    <p class="js-subtitle">
                        Acciones disponibles según tus permisos.
                    </p>
                </div>

                <div class="js-grid js-grid-3">

                    @foreach ($resumen['accesos_rapidos'] as $acceso)

                        @php
                            $restringido =
                                $acceso['requiere_sucursal']
                                && $sucursalOperativa === null;
                        @endphp

                        <article class="js-card">

                            <div class="flex items-start gap-3">

                                <span class="js-text-primary">
                                    <x-layout.icon
                                        :name="$acceso['icono']"
                                    />
                                </span>

                                <div class="min-w-0 flex-1">

                                    <h3 class="js-card-title">
                                        {{ $acceso['nombre'] }}
                                    </h3>

                                    <p class="js-card-description">
                                        {{ $acceso['descripcion'] }}
                                    </p>

                                </div>

                            </div>

                            <div class="mt-4">

                                @if ($restringido)

                                    <span class="js-badge js-badge-warning">
                                        Sucursal requerida
                                    </span>

                                @elseif (
                                    ! empty($acceso['ruta'])
                                    && \Illuminate\Support\Facades\Route::has($acceso['ruta'])
                                )

                                    <a
                                        href="{{ route($acceso['ruta']) }}"
                                        class="js-button js-button-secondary js-button-sm"
                                    >
                                        Abrir
                                    </a>

                                @else

                                    <span class="js-badge">
                                        Próximamente
                                    </span>

                                @endif

                            </div>

                        </article>

                    @endforeach

                </div>

            </section>

        @endif

        {{-- Ventas recientes --}}

        @if ($resumen['ventas_recientes'] !== null)

            <section class="js-card js-stack">

                <div>
                    <h2 class="js-section-title">
                        Ventas recientes
                    </h2>

                    <p class="js-subtitle">
                        Últimas ventas completadas autorizadas.
                    </p>
                </div>

                @if (count($resumen['ventas_recientes']) === 0)

                    <div class="js-empty-state">

                        <span class="js-text-muted">
                            <x-layout.icon name="cart" :size="32" />
                        </span>

                        <p class="js-empty-state-title mt-3">
                            Sin ventas recientes
                        </p>

                        <p class="js-text-small">
                            Todavía no hay ventas completadas
                            para mostrar.
                        </p>

                    </div>

                @else

                    <div class="js-table-wrapper">

                        <table class="js-table">

                            <thead>
                                <tr>
                                    <th>Folio</th>
                                    <th>Fecha</th>
                                    <th>Importe</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>

                            <tbody>

                                @foreach ($resumen['ventas_recientes'] as $venta)

                                    <tr>

                                        <td>
                                            {{ $venta->folio }}
                                        </td>

                                        <td>
                                            {{ $formatoFecha($venta->created_at) }}
                                        </td>

                                        <td class="js-text-numeric">
                                            {{ $formatoMoneda($venta->total) }}
                                            MXN
                                        </td>

                                        <td>

                                            <span class="js-badge js-badge-success">
                                                Completada
                                            </span>

                                        </td>

                                    </tr>

                                @endforeach

                            </tbody>

                        </table>

                    </div>

                @endif

            </section>

        @endif

        {{-- Identificación del usuario --}}

        <section class="js-stack">

            <div>
                <h2 class="js-section-title">
                    Información de sesión
                </h2>

                <p class="js-subtitle">
                    Identificación y contexto operativo actual.
                </p>
            </div>

            <div class="js-grid js-grid-3">

                <article class="js-card">

                    <p class="js-text-muted">
                        Usuario
                    </p>

                    <p class="font-semibold mt-2 break-words">
                        {{ $usuario->username }}
                    </p>

                </article>

                <article class="js-card">

                    <p class="js-text-muted">
                        Código de empleado
                    </p>

                    <p class="font-semibold mt-2">
                        {{ $usuario->codigo_empleado }}
                    </p>

                </article>

                <article class="js-card">

                    <p class="js-text-muted">
                        Rol asignado
                    </p>

                    <p class="font-semibold mt-2">
                        {{ $usuario->role->nombre }}
                    </p>

                </article>

                <article class="js-card">

                    <p class="js-text-muted">
                        Estado de la cuenta
                    </p>

                    <span class="js-badge js-badge-success mt-2">
                        Activo
                    </span>

                </article>

                <article class="js-card">

                    <p class="js-text-muted">
                        Sucursal operativa
                    </p>

                    <p class="font-semibold mt-2">
                        {{ $sucursalOperativa?->nombre
                            ?? 'Sin sucursal operativa' }}
                    </p>

                </article>

                <article class="js-card">

                    <p class="js-text-muted">
                        Código de sucursal
                    </p>

                    <p class="font-semibold mt-2">
                        {{ $sucursalOperativa?->codigo
                            ?? 'No asignado' }}
                    </p>

                </article>

            </div>

        </section>

        {{-- Módulos autorizados --}}

        <section class="js-stack">

            <div>
                <h2 class="js-section-title">
                    Módulos autorizados
                </h2>

                <p class="js-subtitle">
                    Módulos disponibles según los permisos
                    de tu rol.
                </p>
            </div>

            @if ($modulos->isEmpty())

                <div class="js-card js-empty-state">

                    <p class="js-empty-state-title">
                        Sin módulos autorizados
                    </p>

                    <p class="js-text-small">
                        Contacta con el administrador
                        para revisar tus permisos.
                    </p>

                </div>

            @else

                <div class="js-grid js-grid-3">

                    @foreach ($modulos as $modulo)

                        @php
                            $restringido =
                                $modulo['requiere_sucursal']
                                && $sucursalOperativa === null;

                            $ruta = $modulo['ruta'] ?? null;

                            $disponible =
                                ! $restringido
                                && $ruta !== null
                                && \Illuminate\Support\Facades\Route::has($ruta);
                        @endphp

                        <article class="js-card js-module-card">

                            <div class="flex items-start gap-3">

                                <span class="js-text-primary">
                                    <x-layout.icon
                                        :name="$modulo['icono']"
                                    />
                                </span>

                                <div class="min-w-0 flex-1">

                                    <h3 class="module-title">
                                        {{ $modulo['nombre'] }}
                                    </h3>

                                    <p class="js-card-description">
                                        {{ $modulo['descripcion'] }}
                                    </p>

                                </div>

                            </div>

                            <div class="mt-4">

                                @if ($restringido)

                                    <span class="js-badge js-badge-warning">
                                        Sucursal requerida
                                    </span>

                                @elseif ($disponible)

                                    <a
                                        href="{{ route($ruta) }}"
                                        class="js-button js-button-secondary js-button-sm"
                                    >
                                        Abrir módulo
                                    </a>

                                @else

                                    <span class="js-badge">
                                        Próximamente
                                    </span>

                                @endif

                            </div>

                        </article>

                    @endforeach

                </div>

            @endif

        </section>

        <p class="js-text-small text-center">
            JoySys MVP 1.0 — Dashboard principal
        </p>

    </div>

@endsection