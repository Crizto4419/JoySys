@extends('layouts.guest')

@section('title', 'Iniciar sesión')

@section('content')

    <x-ui.card class="js-guest-card">

        <div class="js-guest-card-heading">

            <div class="js-guest-emblem" aria-hidden="true">

                <x-layout.icon
                    name="lock"
                    :size="32"
                />

            </div>

            <h1 class="js-guest-title">
                Iniciar sesión
            </h1>

            <p class="js-guest-description">
                Ingresa tus credenciales para acceder a JoySys.
            </p>

        </div>

        @if (session('status'))

            <x-ui.alert
                variant="success"
                class="mb-5"
            >
                {{ session('status') }}
            </x-ui.alert>

        @endif

        @if ($errors->any())

            <x-ui.alert
                variant="danger"
                title="No se pudo iniciar sesión"
                class="mb-5"
            >
                <p>
                    Revisa los mensajes del formulario
                    e intenta nuevamente.
                </p>

                @foreach ($errors->getMessages() as $campo => $mensajes)

                    @if (! in_array($campo, ['username', 'password'], true))

                        @foreach ($mensajes as $mensaje)

                            <p>{{ $mensaje }}</p>

                        @endforeach

                    @endif

                @endforeach

            </x-ui.alert>

        @endif

        <form
            method="POST"
            action="{{ route('login.store') }}"
            class="js-guest-form"
        >

            @csrf

            <x-forms.input
                name="username"
                id="username"
                label="Usuario"
                type="text"
                maxlength="50"
                autocomplete="username"
                placeholder="Nombre de usuario"
                :required="true"
                autofocus
            />

            <x-forms.input
                name="password"
                id="password"
                label="Contraseña"
                type="password"
                maxlength="72"
                autocomplete="current-password"
                placeholder="Introduce tu contraseña"
                :required="true"
            />

            <x-ui.button
                type="submit"
                variant="primary"
                icon="lock"
            >
                Iniciar sesión
            </x-ui.button>

        </form>

        <p class="js-guest-notice">
            Acceso exclusivo para personal autorizado.
        </p>

    </x-ui.card>

@endsection