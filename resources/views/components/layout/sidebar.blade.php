@props(['sucursalOperativa' => null])

@php
    $modulos = collect(config('joysys.navegacion', []))
        ->filter(
            fn ($modulo) => auth()->user()->can(
                'joysys.permiso',
                $modulo['permiso']
            )
        );

    $grupos = [
        'operaciones' => 'Operaciones',
        'administracion' => 'Administración',
    ];

    $dashboard = [
        'nombre' => 'Dashboard',
        'permiso' => 'dashboard.ver',
        'icono' => 'dashboard',
        'ruta' => 'dashboard',
        'requiere_sucursal' => false,
    ];
@endphp

<aside
    id="joysys-sidebar"
    class="js-sidebar"
    data-sidebar
    aria-label="Menú lateral"
    tabindex="-1"
>
    <div class="js-sidebar-brand">

        <div class="js-brand">
            <x-layout.icon name="gem" :size="28"/>

            <span class="js-brand-label">JoySys</span>
        </div>

        <button
            type="button"
            class="js-button js-button-ghost js-button-icon js-sidebar-close"
            data-sidebar-close
            aria-label="Cerrar menú"
        >
            <x-layout.icon name="close"/>
        </button>

    </div>

    <nav
        class="js-sidebar-nav"
        aria-label="Navegación principal"
    >
        @can('joysys.permiso', 'dashboard.ver')
            <div class="js-nav-group">

                <div class="js-nav-group-title">
                    General
                </div>

                <x-layout.nav-item
                    :modulo="$dashboard"
                    :sucursal-operativa="$sucursalOperativa"
                />

            </div>
        @endcan

        @foreach ($grupos as $clave => $nombre)
            @php
                $modulosGrupo = $modulos->where(
                    'grupo',
                    $clave
                );
            @endphp

            @if ($modulosGrupo->isNotEmpty())
                <div class="js-nav-group">

                    <div class="js-nav-group-title">
                        {{ $nombre }}
                    </div>

                    @foreach ($modulosGrupo as $modulo)
                        <x-layout.nav-item
                            :modulo="$modulo"
                            :sucursal-operativa="$sucursalOperativa"
                        />
                    @endforeach

                </div>
            @endif
        @endforeach

    </nav>

    <div class="js-sidebar-footer">
        <span class="js-sidebar-version">
            JoySys {{ config('joysys.version') }}
        </span>
    </div>

</aside>