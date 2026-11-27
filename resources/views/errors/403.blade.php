@extends('layouts.guest')

@section('title', 'Acceso denegado')

@section('content')

    <x-ui.card class="js-guest-card">

        <div class="js-guest-card-heading">

            <div
                class="js-guest-emblem"
                aria-hidden="true"
            >
                <x-layout.icon
                    name="shield"
                    :size="32"
                />
            </div>

            <div class="js-guest-error-code">
                403
            </div>

            <h1 class="js-guest-title">
                Acceso denegado
            </h1>

        </div>

        <div class="js-guest-error-content">

            <p>
                No tienes los permisos necesarios para acceder
                a esta sección o realizar esta operación.
            </p>

            <p>
                Si consideras que necesitas acceso,
                contacta al administrador del sistema.
            </p>

        </div>

        <div class="js-guest-actions">

            @auth

                @can('joysys.permiso', 'dashboard.ver')

                    <x-ui.button
                        variant="primary"
                        icon="dashboard"
                        :href="route('dashboard')"
                    >
                        Volver al dashboard
                    </x-ui.button>

                @endcan

                <form
                    method="POST"
                    action="{{ route('logout') }}"
                >

                    @csrf

                    <x-ui.button
                        type="submit"
                        variant="secondary"
                        icon="logout"
                    >
                        Cerrar sesión
                    </x-ui.button>

                </form>

            @else

                <x-ui.button
                    variant="primary"
                    icon="lock"
                    :href="route('login')"
                >
                    Iniciar sesión
                </x-ui.button>

            @endauth

        </div>

        <p class="js-guest-notice">
            Acceso restringido a personal autorizado.
        </p>

    </x-ui.card>

@endsection