<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>@yield('title', 'Dashboard') | JoySys</title>

    <script>
        (() => {
            const raiz = document.documentElement;

            let tema = null;
            let menuContraido = null;

            try {
                tema = localStorage.getItem('joysys.tema');

                menuContraido = localStorage.getItem(
                    'joysys.sidebar.collapsed'
                );
            } catch {
                // Continuar con las preferencias predeterminadas.
            }

            if (tema !== 'light' && tema !== 'dark') {
                tema = window.matchMedia(
                    '(prefers-color-scheme: dark)'
                ).matches ? 'dark' : 'light';
            }

            raiz.dataset.theme = tema;

            const escritorio = window.matchMedia(
                '(min-width: 1024px)'
            ).matches;

            raiz.dataset.sidebarCollapsed = escritorio
                ? String(menuContraido === 'true')
                : 'true';
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @stack('styles')
</head>

<body class="js-app-body">

    <a href="#joysys-main" class="js-skip-link">
        Saltar al contenido principal
    </a>

    <div class="js-app-shell">

        <x-layout.sidebar
            :sucursal-operativa="$sucursalOperativa ?? null"
        />

        <div
            class="js-sidebar-overlay"
            data-sidebar-overlay
            hidden
            aria-hidden="true"
        ></div>

        <div class="js-app-workspace" data-app-workspace>

            <x-layout.header
                :sucursal-operativa="$sucursalOperativa ?? null"
            />

            <main
                id="joysys-main"
                class="js-app-main"
                tabindex="-1"
            >
                <div class="js-container">
                    @yield('content')
                </div>
            </main>

            <footer class="js-app-footer">
                JoySys {{ config('joysys.version') }}
            </footer>

        </div>

    </div>

    @stack('scripts')

</body>
</html>