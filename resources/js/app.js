import './bootstrap';

/* ==================================================
   JOYSYS — SISTEMA DE INTERACTIVIDAD
   Versión 1.0
   ================================================== */

const CLAVE_TEMA = 'joysys.tema';

const TEMAS = Object.freeze({
    CLARO: 'light',
    OSCURO: 'dark',
});

const elementoRaiz = document.documentElement;

const preferenciaSistema = window.matchMedia(
    '(prefers-color-scheme: dark)'
);

/* ==================================================
   1. VALIDACIÓN DE TEMAS
   ================================================== */

function temaValido(tema) {
    return tema === TEMAS.CLARO || tema === TEMAS.OSCURO;
}

/* ==================================================
   2. ALMACENAMIENTO LOCAL
   ================================================== */

function obtenerTemaGuardado() {
    try {
        const tema = window.localStorage.getItem(CLAVE_TEMA);

        return temaValido(tema) ? tema : null;
    } catch {
        return null;
    }
}

function guardarTema(tema) {
    if (!temaValido(tema)) {
        return false;
    }

    try {
        window.localStorage.setItem(CLAVE_TEMA, tema);

        return true;
    } catch {
        return false;
    }
}

function eliminarTemaGuardado() {
    try {
        window.localStorage.removeItem(CLAVE_TEMA);

        return true;
    } catch {
        return false;
    }
}

/* ==================================================
   3. PREFERENCIA DEL SISTEMA
   ================================================== */

function obtenerTemaSistema() {
    return preferenciaSistema.matches
        ? TEMAS.OSCURO
        : TEMAS.CLARO;
}

/* ==================================================
   4. ESTADO DEL TEMA
   ================================================== */

let temaSeleccionado = obtenerTemaGuardado();

function obtenerTemaActual() {
    const tema = elementoRaiz.dataset.theme;

    return temaValido(tema)
        ? tema
        : obtenerTemaSistema();
}

/* ==================================================
   5. ACTUALIZACIÓN DE CONTROLES
   ================================================== */

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
            const tema = control.dataset.themeSet;

            control.setAttribute(
                'aria-pressed',
                String(tema === temaActual)
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

/* ==================================================
   6. APLICACIÓN DEL TEMA
   ================================================== */

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

/* ==================================================
   7. SELECCIÓN MANUAL
   ================================================== */

function establecerTema(tema) {
    if (!temaValido(tema)) {
        return false;
    }

    temaSeleccionado = tema;

    guardarTema(tema);

    return aplicarTema(tema);
}

function alternarTema() {
    const temaActual = obtenerTemaActual();

    const nuevoTema = temaActual === TEMAS.OSCURO
        ? TEMAS.CLARO
        : TEMAS.OSCURO;

    return establecerTema(nuevoTema);
}

/* ==================================================
   8. RESTABLECER PREFERENCIA DEL SISTEMA
   ================================================== */

function restablecerTema() {
    temaSeleccionado = null;

    eliminarTemaGuardado();

    return aplicarTema(obtenerTemaSistema());
}

/* ==================================================
   9. EVENTOS DE LOS CONTROLES
   ================================================== */

document.addEventListener('click', (evento) => {
    if (!(evento.target instanceof Element)) {
        return;
    }

    const control = evento.target.closest(
        '[data-theme-toggle], [data-theme-set], [data-theme-reset]'
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
    }
});

/* ==================================================
   10. CAMBIOS EN LA PREFERENCIA DEL SISTEMA
   ================================================== */

preferenciaSistema.addEventListener('change', () => {
    if (temaSeleccionado === null) {
        aplicarTema(obtenerTemaSistema());
    }
});

/* ==================================================
   11. SINCRONIZACIÓN ENTRE PESTAÑAS
   ================================================== */

window.addEventListener('storage', (evento) => {
    if (evento.key !== CLAVE_TEMA && evento.key !== null) {
        return;
    }

    try {
        if (evento.storageArea !== window.localStorage) {
            return;
        }
    } catch {
        return;
    }

    temaSeleccionado = obtenerTemaGuardado();

    aplicarTema(
        temaSeleccionado ?? obtenerTemaSistema()
    );
});

/* ==================================================
   12. INICIALIZACIÓN
   ================================================== */

function inicializarJoySys() {
    aplicarTema(
        temaSeleccionado ?? obtenerTemaSistema()
    );
}

inicializarJoySys();

if (document.readyState === 'loading') {
    document.addEventListener(
        'DOMContentLoaded',
        actualizarControlesTema,
        { once: true }
    );
}

/* ==================================================
   13. INTERFAZ PÚBLICA DEL SISTEMA
   ================================================== */

window.JoySys = Object.freeze({
    tema: Object.freeze({
        obtener: obtenerTemaActual,
        establecer: establecerTema,
        alternar: alternarTema,
        restablecer: restablecerTema,
    }),
});