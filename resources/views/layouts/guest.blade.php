<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>@yield('title', 'Acceso') | JoySys</title>

    <script>
        (() => {
            const raiz = document.documentElement;

            let tema = null;

            try {
                tema = localStorage.getItem('joysys.tema');
            } catch {
                // Utilizar la preferencia del dispositivo.
            }

            if (tema !== 'light' && tema !== 'dark') {
                tema = window.matchMedia(
                    '(prefers-color-scheme: dark)'
                ).matches ? 'dark' : 'light';
            }

            raiz.dataset.theme = tema;
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @stack('styles')
</head>

<body class="js-guest-body">

    <a href="#joysys-main" class="js-skip-link">
        Saltar al contenido principal
    </a>

    <div class="js-guest-shell">

        <header class="js-guest-header">

            <div class="js-brand">
                <x-layout.icon name="gem" :size="28" />

                <span>JoySys</span>
            </div>

            <x-layout.theme-toggle />

        </header>

        <main
            id="joysys-main"
            class="js-guest-main"
            tabindex="-1"
        >
            <div class="js-guest-content">

                @yield('content')

            </div>
        </main>

        <footer class="js-guest-footer">

            <p>
                JoySys {{ config('joysys.version') }}
            </p>

            <p>
                Sistema de gestión de joyería
            </p>

        </footer>

    </div>

    @stack('scripts')

</body>
</html>