@props(['sucursalOperativa' => null])

@php
    $usuario = auth()->user();

    $inicial = mb_strtoupper(
        mb_substr($usuario->name, 0, 1)
    );
@endphp

<header class="js-header">

    <div class="js-header-left">

        <button
            type="button"
            class="js-button js-button-ghost js-button-icon"
            data-sidebar-toggle
            aria-controls="joysys-sidebar"
            aria-expanded="false"
            aria-label="Alternar menú lateral"
            title="Alternar menú lateral"
        >
            <x-layout.icon
                name="panel"
                class="js-desktop-menu-icon"
            />

            <x-layout.icon
                name="menu"
                class="js-mobile-menu-icon"
            />
        </button>

        <div class="js-header-branch">

            <x-layout.icon
                name="store"
                class="js-text-primary"
            />

            <div class="js-header-branch-details">

                <div class="js-header-primary-text">
                    {{ $sucursalOperativa?->nombre ?? 'Sin sucursal operativa' }}
                </div>

                <div class="js-header-secondary-text">
                    {{ $sucursalOperativa?->codigo ?? 'No asignado' }}
                </div>

            </div>

        </div>

    </div>

    <div class="js-header-right">

        <x-layout.theme-toggle/>

        <div class="js-header-divider"></div>

        <div class="js-header-user">

            <div class="js-avatar" aria-hidden="true">
                {{ $inicial }}
            </div>

            <div class="js-header-user-details">

                <div class="js-header-primary-text">
                    {{ $usuario->name }}
                </div>

                <div class="js-header-secondary-text">
                    {{ $usuario->role->nombre }}
                </div>

            </div>

        </div>

        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <button
                type="submit"
                class="js-button js-button-ghost js-button-icon"
                aria-label="Cerrar sesión"
                title="Cerrar sesión"
            >
                <x-layout.icon name="logout"/>
            </button>
        </form>

    </div>

</header>