@props([
    'modulo',
    'sucursalOperativa' => null,
])

@php
    $ruta = $modulo['ruta'] ?? null;

    $restringido = $modulo['requiere_sucursal']
        && $sucursalOperativa === null;

    $disponible = !$restringido
        && $ruta !== null
        && \Illuminate\Support\Facades\Route::has($ruta);

    $activo = $disponible && request()->routeIs($ruta);

    $estado = $restringido
        ? 'Sucursal requerida'
        : 'Próximamente';
@endphp

@can('joysys.permiso', $modulo['permiso'])

    @if ($disponible)
        <a
            href="{{ route($ruta) }}"
            @class([
                'js-nav-item',
                'js-nav-item-active' => $activo,
            ])
            title="{{ $modulo['nombre'] }}"
            aria-label="{{ $modulo['nombre'] }}"
            @if ($activo)
                aria-current="page"
            @endif
        >
            <x-layout.icon :name="$modulo['icono']"/>

            <span class="js-nav-label">
                {{ $modulo['nombre'] }}
            </span>
        </a>
    @else
        <span
            class="js-nav-item js-nav-item-disabled"
            aria-disabled="true"
            title="{{ $modulo['nombre'] }} — {{ $estado }}"
            aria-label="{{ $modulo['nombre'] }} — {{ $estado }}"
        >
            <x-layout.icon :name="$modulo['icono']"/>

            <span class="js-nav-label">
                {{ $modulo['nombre'] }}
            </span>

            <span class="js-nav-status">
                <x-layout.icon
                    :name="$restringido ? 'lock' : 'clock'"
                    :size="14"
                />
            </span>
        </span>
    @endif

@endcan