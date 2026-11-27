import './bootstrap';
import './components';

/* ==================================================
   JOYSYS — SISTEMA DE INTERACTIVIDAD
   ================================================== */

const CLAVE_TEMA = 'joysys.tema';
const CLAVE_MENU = 'joysys.sidebar.collapsed';

const TEMAS = Object.freeze({
    CLARO: 'light',
    OSCURO: 'dark',
});

const elementoRaiz = document.documentElement;

const preferenciaSistema = window.matchMedia(
    '(prefers-color-scheme: dark)'
);

const pantallaMovil = window.matchMedia(
    '(max-width: 767px)'
);

const pantallaEscritorio = window.matchMedia(
    '(min-width: 1024px)'
);

/* 1. ALMACENAMIENTO LOCAL */

function leerPreferencia(clave) {
    try {
        return window.localStorage.getItem(clave);
    } catch {
        return null;
    }
}

function guardarPreferencia(clave, valor) {
    try {
        window.localStorage.setItem(clave, valor);
        return true;
    } catch {
        return false;
    }
}

function eliminarPreferencia(clave) {
    try {
        window.localStorage.removeItem(clave);
        return true;
    } catch {
        return false;
    }
}

/* 2. SISTEMA DE TEMAS */

function temaValido(tema) {
    return tema === TEMAS.CLARO || tema === TEMAS.OSCURO;
}

function obtenerTemaGuardado() {
    const tema = leerPreferencia(CLAVE_TEMA);

    return temaValido(tema) ? tema : null;
}

function obtenerTemaSistema() {
    return preferenciaSistema.matches
        ? TEMAS.OSCURO
        : TEMAS.CLARO;
}

let temaSeleccionado = obtenerTemaGuardado();

function obtenerTemaActual() {
    const tema = elementoRaiz.dataset.theme;

    return temaValido(tema)
        ? tema
        : obtenerTemaSistema();
}

function actualizarControlesTema() {
    const temaActual = obtenerTemaActual();

    const modoOscuro = temaActual === TEMAS.OSCURO;

    document
        .querySelectorAll('[data-theme-toggle]')
        .forEach((control) => {
            const etiqueta = modoOscuro
                ? 'Cambiar a modo claro'
                : 'Cambiar a modo oscuro';

            control.setAttribute('aria-label', etiqueta);
            control.setAttribute('title', etiqueta);
        });

    document
        .querySelectorAll('[data-theme-set]')
        .forEach((control) => {
            control.setAttribute(
                'aria-pressed',
                String(control.dataset.themeSet === temaActual)
            );
        });

    document
        .querySelectorAll('[data-theme-reset]')
        .forEach((control) => {
            control.setAttribute(
                'aria-pressed',
                String(temaSeleccionado === null)
            );
        });

    document
        .querySelectorAll('[data-theme-label]')
        .forEach((elemento) => {
            elemento.textContent = modoOscuro
                ? 'Modo oscuro'
                : 'Modo claro';
        });
}

function aplicarTema(tema) {
    if (!temaValido(tema)) {
        return false;
    }

    const temaAnterior = elementoRaiz.dataset.theme;

    elementoRaiz.dataset.theme = tema;

    actualizarControlesTema();

    if (temaAnterior !== tema) {
        document.dispatchEvent(
            new CustomEvent('joysys:tema-cambiado', {
                detail: {
                    tema,
                    temaAnterior: temaValido(temaAnterior)
                        ? temaAnterior
                        : null,
                },
            })
        );
    }

    return true;
}

function establecerTema(tema) {
    if (!temaValido(tema)) {
        return false;
    }

    temaSeleccionado = tema;

    guardarPreferencia(CLAVE_TEMA, tema);

    return aplicarTema(tema);
}

function alternarTema() {
    return establecerTema(
        obtenerTemaActual() === TEMAS.OSCURO
            ? TEMAS.CLARO
            : TEMAS.OSCURO
    );
}

function restablecerTema() {
    temaSeleccionado = null;

    eliminarPreferencia(CLAVE_TEMA);

    return aplicarTema(obtenerTemaSistema());
}

/* 3. ESTADO DEL MENÚ LATERAL */

let menuContraidoEscritorio =
    leerPreferencia(CLAVE_MENU) === 'true';

let menuContraidoTablet = true;

let menuMovilAbierto = false;

let focoAnterior = null;

function obtenerSidebar() {
    return document.querySelector('[data-sidebar]');
}

function obtenerOverlay() {
    return document.querySelector('[data-sidebar-overlay]');
}

function obtenerWorkspace() {
    return document.querySelector('[data-app-workspace]');
}

function obtenerMenuContraido() {
    return elementoRaiz.dataset.sidebarCollapsed === 'true';
}

function actualizarControlesMenu() {
    const expandido = pantallaMovil.matches
        ? menuMovilAbierto
        : !obtenerMenuContraido();

    const etiqueta = pantallaMovil.matches
        ? (expandido ? 'Cerrar menú' : 'Abrir menú')
        : (expandido ? 'Contraer menú' : 'Expandir menú');

    document
        .querySelectorAll('[data-sidebar-toggle]')
        .forEach((control) => {
            control.setAttribute(
                'aria-expanded',
                String(expandido)
            );

            control.setAttribute('aria-label', etiqueta);
            control.setAttribute('title', etiqueta);
        });
}

function aplicarMenuContraido(contraido) {
    elementoRaiz.dataset.sidebarCollapsed = String(
        Boolean(contraido)
    );

    actualizarControlesMenu();
}

function establecerMenuContraido(contraido) {
    const valor = Boolean(contraido);

    if (pantallaMovil.matches) {
        return;
    }

    if (pantallaEscritorio.matches) {
        menuContraidoEscritorio = valor;

        guardarPreferencia(
            CLAVE_MENU,
            String(valor)
        );
    } else {
        menuContraidoTablet = valor;
    }

    aplicarMenuContraido(valor);
}

/* 4. MENÚ MÓVIL */

function abrirMenuMovil() {
    const sidebar = obtenerSidebar();
    const overlay = obtenerOverlay();
    const workspace = obtenerWorkspace();

    if (
        !pantallaMovil.matches ||
        !sidebar ||
        !overlay ||
        menuMovilAbierto
    ) {
        return;
    }

    focoAnterior = document.activeElement;

    menuMovilAbierto = true;

    elementoRaiz.dataset.sidebarOpen = 'true';

    overlay.hidden = false;

    sidebar.inert = false;

    sidebar.removeAttribute('aria-hidden');

    sidebar.setAttribute('role', 'dialog');
    sidebar.setAttribute('aria-modal', 'true');

    if (workspace) {
        workspace.inert = true;
    }

    const botonCerrar = sidebar.querySelector(
        '[data-sidebar-close]'
    );

    if (botonCerrar) {
        botonCerrar.focus();
    } else {
        sidebar.focus();
    }

    actualizarControlesMenu();
}

function cerrarMenuMovil(restaurarFoco = true) {
    const sidebar = obtenerSidebar();
    const overlay = obtenerOverlay();
    const workspace = obtenerWorkspace();

    const estabaAbierto = menuMovilAbierto;

    menuMovilAbierto = false;

    elementoRaiz.dataset.sidebarOpen = 'false';

    if (overlay) {
        overlay.hidden = true;
    }

    if (workspace) {
        workspace.inert = false;
    }

    if (
        estabaAbierto &&
        restaurarFoco &&
        focoAnterior instanceof HTMLElement &&
        focoAnterior.isConnected
    ) {
        focoAnterior.focus();
    }

    if (sidebar) {
        sidebar.removeAttribute('role');
        sidebar.removeAttribute('aria-modal');

        sidebar.inert = pantallaMovil.matches;

        if (pantallaMovil.matches) {
            sidebar.setAttribute('aria-hidden', 'true');
        } else {
            sidebar.removeAttribute('aria-hidden');
        }
    }

    focoAnterior = null;

    actualizarControlesMenu();
}

function alternarMenu() {
    if (pantallaMovil.matches) {
        if (menuMovilAbierto) {
            cerrarMenuMovil();
        } else {
            abrirMenuMovil();
        }

        return;
    }

    establecerMenuContraido(
        !obtenerMenuContraido()
    );
}

/* 5. ADAPTACIÓN A LA RESOLUCIÓN */

function adaptarMenuPantalla() {
    const sidebar = obtenerSidebar();

    const focoDentroDelMenu = sidebar
        && sidebar.contains(document.activeElement);

    cerrarMenuMovil(false);

    if (pantallaMovil.matches) {
        aplicarMenuContraido(false);
    } else if (pantallaEscritorio.matches) {
        aplicarMenuContraido(
            menuContraidoEscritorio
        );
    } else {
        aplicarMenuContraido(
            menuContraidoTablet
        );
    }

    if (focoDentroDelMenu && pantallaMovil.matches) {
        document
            .querySelector('[data-sidebar-toggle]')
            ?.focus();
    } else if (
        focoDentroDelMenu &&
        document.activeElement?.matches('[data-sidebar-close]')
    ) {
        document
            .querySelector('[data-sidebar-toggle]')
            ?.focus();
    }
}

/* 6. GESTIÓN DEL FOCO */

function obtenerElementosEnfocables(contenedor) {
    return Array.from(
        contenedor.querySelectorAll(
            'a[href], button, input, select, textarea, [tabindex]'
        )
    ).filter((elemento) => {
        return !elemento.matches(':disabled')
            && elemento.tabIndex >= 0
            && elemento.getClientRects().length > 0
            && !elemento.closest('[inert]');
    });
}

document.addEventListener('keydown', (evento) => {
    if (!menuMovilAbierto) {
        return;
    }

    if (evento.key === 'Escape') {
        evento.preventDefault();

        cerrarMenuMovil();

        return;
    }

    if (evento.key !== 'Tab') {
        return;
    }

    const sidebar = obtenerSidebar();

    if (!sidebar) {
        return;
    }

    const elementos = obtenerElementosEnfocables(sidebar);

    if (elementos.length === 0) {
        evento.preventDefault();

        sidebar.focus();

        return;
    }

    const primero = elementos[0];

    const ultimo = elementos[elementos.length - 1];

    const actual = document.activeElement;

    if (!elementos.includes(actual)) {
        evento.preventDefault();

        (evento.shiftKey ? ultimo : primero).focus();

        return;
    }

    if (evento.shiftKey && actual === primero) {
        evento.preventDefault();

        ultimo.focus();
    } else if (!evento.shiftKey && actual === ultimo) {
        evento.preventDefault();

        primero.focus();
    }
});

/* 7. EVENTOS DE INTERACCIÓN */

document.addEventListener('click', (evento) => {
    if (!(evento.target instanceof Element)) {
        return;
    }

    const control = evento.target.closest(
        '[data-theme-toggle], ' +
        '[data-theme-set], ' +
        '[data-theme-reset], ' +
        '[data-sidebar-toggle], ' +
        '[data-sidebar-close], ' +
        '[data-sidebar-overlay]'
    );

    if (!control) {
        return;
    }

    if (
        control.matches(':disabled') ||
        control.getAttribute('aria-disabled') === 'true'
    ) {
        return;
    }

    evento.preventDefault();

    if (control.hasAttribute('data-theme-toggle')) {
        alternarTema();

        return;
    }

    if (control.hasAttribute('data-theme-set')) {
        establecerTema(control.dataset.themeSet);

        return;
    }

    if (control.hasAttribute('data-theme-reset')) {
        restablecerTema();

        return;
    }

    if (control.hasAttribute('data-sidebar-toggle')) {
        alternarMenu();

        return;
    }

    if (
        control.hasAttribute('data-sidebar-close') ||
        control.hasAttribute('data-sidebar-overlay')
    ) {
        cerrarMenuMovil();
    }
});

/* 8. PREFERENCIAS DEL DISPOSITIVO */

preferenciaSistema.addEventListener('change', () => {
    if (temaSeleccionado === null) {
        aplicarTema(obtenerTemaSistema());
    }
});

pantallaMovil.addEventListener(
    'change',
    adaptarMenuPantalla
);

pantallaEscritorio.addEventListener(
    'change',
    adaptarMenuPantalla
);

/* 9. SINCRONIZACIÓN ENTRE PESTAÑAS */

window.addEventListener('storage', (evento) => {
    if (
        evento.key !== CLAVE_TEMA &&
        evento.key !== CLAVE_MENU &&
        evento.key !== null
    ) {
        return;
    }

    try {
        if (evento.storageArea !== window.localStorage) {
            return;
        }
    } catch {
        return;
    }

    if (
        evento.key === CLAVE_TEMA ||
        evento.key === null
    ) {
        temaSeleccionado = obtenerTemaGuardado();

        aplicarTema(
            temaSeleccionado ?? obtenerTemaSistema()
        );
    }

    if (
        evento.key === CLAVE_MENU ||
        evento.key === null
    ) {
        menuContraidoEscritorio =
            leerPreferencia(CLAVE_MENU) === 'true';

        if (pantallaEscritorio.matches) {
            aplicarMenuContraido(
                menuContraidoEscritorio
            );
        }
    }
});

/* 10. INICIALIZACIÓN */

function inicializarJoySys() {
    aplicarTema(
        temaSeleccionado ?? obtenerTemaSistema()
    );

    adaptarMenuPantalla();
}

if (document.readyState === 'loading') {
    document.addEventListener(
        'DOMContentLoaded',
        inicializarJoySys,
        { once: true }
    );
} else {
    inicializarJoySys();
}

/* 11. INTERFAZ PÚBLICA */

window.JoySys = Object.freeze({

    tema: Object.freeze({
        obtener: obtenerTemaActual,
        establecer: establecerTema,
        alternar: alternarTema,
        restablecer: restablecerTema,
    }),

    menu: Object.freeze({
        contraido: obtenerMenuContraido,
        establecer: establecerMenuContraido,
        alternar: alternarMenu,
        abrir: abrirMenuMovil,
        cerrar: cerrarMenuMovil,
    }),

});