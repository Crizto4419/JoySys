@extends('layouts.app')

@section('title', 'Dashboard')

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
    @endphp

    <div class="js-stack">

        {{-- BIENVENIDA --}}

        <section class="js-dashboard-section">

            <div>
                <h1 class="js-page-title">
                    Bienvenido, {{ $usuario->name }}
                </h1>

                <p class="js-subtitle mt-2">
                    Has iniciado sesión correctamente en JoySys.
                </p>
            </div>

            {{-- INFORMACIÓN DEL EMPLEADO --}}

            <div class="js-grid js-grid-3">

                <div class="js-card">

                    <div class="js-dashboard-label">
                        Usuario
                    </div>

                    <div class="js-dashboard-value">
                        {{ $usuario->username }}
                    </div>

                </div>

                <div class="js-card">

                    <div class="js-dashboard-label">
                        Código de empleado
                    </div>

                    <div class="js-dashboard-value">
                        {{ $usuario->codigo_empleado }}
                    </div>

                </div>

                <div class="js-card">

                    <div class="js-dashboard-label">
                        Rol asignado
                    </div>

                    <div class="js-dashboard-value">
                        {{ $usuario->role->nombre }}
                    </div>

                </div>

                <div class="js-card">

                    <div class="js-dashboard-label">
                        Estado de la cuenta
                    </div>

                    <span class="js-badge js-badge-success">
                        Activo
                    </span>

                </div>

                <div class="js-card">

                    <div class="js-dashboard-label">
                        Sucursal operativa
                    </div>

                    @if ($sucursalOperativa !== null)

                        <div class="js-dashboard-value">
                            {{ $sucursalOperativa->nombre }}
                        </div>

                    @else

                        <div class="js-dashboard-value js-text-warning">
                            Sin sucursal operativa
                        </div>

                    @endif

                </div>

                <div class="js-card">

                    <div class="js-dashboard-label">
                        Código de sucursal
                    </div>

                    <div class="js-dashboard-value">
                        {{ $sucursalOperativa?->codigo ?? 'No asignado' }}
                    </div>

                </div>

            </div>

            {{-- RESTRICCIÓN DE SUCURSAL --}}

            @if ($sucursalOperativa === null)

                <div
                    class="js-alert js-alert-warning"
                    role="status"
                >
                    <x-layout.icon
                        name="alert"
                        :size="22"
                    />

                    <div>

                        <strong>
                            Operaciones restringidas
                        </strong>

                        <p class="mt-1">
                            No tienes una sucursal operativa habilitada.
                            Contacta al administrador para revisar
                            tu asignación.
                        </p>

                    </div>

                </div>

            @endif

        </section>

        {{-- MÓDULOS AUTORIZADOS --}}

        <section class="js-dashboard-section mt-4">

            <div>

                <h2 class="js-section-title">
                    Módulos autorizados
                </h2>

                <p class="js-subtitle mt-1">
                    Los módulos visibles corresponden a los
                    permisos asignados a tu rol.
                </p>

            </div>

            @if ($modulos->isEmpty())

                <div class="js-card">

                    <div class="js-empty-state">

                        <x-layout.icon
                            name="lock"
                            :size="32"
                        />

                        <p>
                            No tienes módulos adicionales autorizados.
                            Contacta al administrador si necesitas acceso.
                        </p>

                    </div>

                </div>

            @else

                <div class="js-grid js-grid-3">

                    @foreach ($modulos as $modulo)

                        @php
                            $restringido =
                                $modulo['requiere_sucursal']
                                && $sucursalOperativa === null;

                            $ruta = $modulo['ruta'];

                            $disponible =
                                !$restringido
                                && $ruta !== null
                                && \Illuminate\Support\Facades\Route::has($ruta);
                        @endphp

                        <article class="js-card js-module-card">

                            <x-layout.icon
                                :name="$modulo['icono']"
                                :size="24"
                                class="js-text-primary"
                            />

                            <h3 class="module-title">
                                {{ $modulo['nombre'] }}
                            </h3>

                            <p class="js-module-description">
                                {{ $modulo['descripcion'] }}
                            </p>

                            @if ($restringido)

                                <span class="js-badge js-badge-warning">

                                    <x-layout.icon
                                        name="lock"
                                        :size="13"
                                    />

                                    Sucursal requerida

                                </span>

                            @elseif ($disponible)

                                <a
                                    href="{{ route($ruta) }}"
                                    class="js-button js-button-primary js-button-sm"
                                >
                                    Abrir módulo
                                </a>

                            @else

                                <span class="js-badge">

                                    <x-layout.icon
                                        name="clock"
                                        :size="13"
                                    />

                                    Próximamente

                                </span>

                            @endif

                        </article>

                    @endforeach

                </div>

            @endif

        </section>

        {{-- AVISO DE VERSIÓN --}}

        <div class="js-card js-text-muted js-text-small mt-4">

            JoySys MVP 1.0 — Dashboard provisional.

            Los módulos operativos se habilitarán conforme
            avance la implementación del sistema.

        </div>

    </div>

@endsection