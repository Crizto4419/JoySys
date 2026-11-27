/* JOYSYS - COMPONENTES INTERACTIVOS */

const focosAnteriores = new WeakMap();

const dialogosRegistrados = new WeakSet();

const iniciosEnBackdrop = new WeakMap();

/* 1. LOCALIZACION DE MODALES */

function obtenerModal(id) {
    if (typeof id !== 'string' || id.trim() === '') {
        return null;
    }

    const elemento = document.getElementById(id);

    return elemento instanceof HTMLDialogElement
        && elemento.hasAttribute('data-js-modal')
        ? elemento
        : null;
}

/* 2. REGISTRO DE EVENTOS */

function registrarModal(modal) {
    if (dialogosRegistrados.has(modal)) {
        return;
    }

    dialogosRegistrados.add(modal);

    modal.addEventListener('cancel', (evento) => {
        if (modal.dataset.closeOnEscape === 'false') {
            evento.preventDefault();
        }
    });

    modal.addEventListener('close', () => {
        const focoAnterior = focosAnteriores.get(modal);

        focosAnteriores.delete(modal);

        iniciosEnBackdrop.delete(modal);

        if (
            focoAnterior instanceof HTMLElement
            && focoAnterior.isConnected
            && !focoAnterior.matches(':disabled')
            && !focoAnterior.closest('[inert]')
            && focoAnterior.getClientRects().length > 0
        ) {
            focoAnterior.focus();
        }

        modal.dispatchEvent(
            new CustomEvent('joysys:modal-cerrado', {
                bubbles: true,
                detail: {
                    id: modal.id,
                    resultado: modal.returnValue,
                },
            })
        );
    });

    modal.addEventListener('pointerdown', (evento) => {
        iniciosEnBackdrop.set(
            modal,
            evento.target === modal
        );
    });

    modal.addEventListener('pointercancel', () => {
        iniciosEnBackdrop.delete(modal);
    });

    modal.addEventListener('click', (evento) => {
        const inicioEnBackdrop =
            iniciosEnBackdrop.get(modal) === true;

        iniciosEnBackdrop.delete(modal);

        if (
            evento.target === modal
            && inicioEnBackdrop
            && modal.dataset.closeOnBackdrop === 'true'
        ) {
            cerrarModal(modal.id);
        }
    });
}

/* 3. APERTURA */

function abrirModal(id, activador = null) {
    const modal = obtenerModal(id);

    if (!modal || modal.open) {
        return false;
    }

    if (document.querySelector('dialog[open]')) {
        return false;
    }

    if (document.documentElement.dataset.sidebarOpen === 'true') {
        window.JoySys?.menu?.cerrar();
    }

    registrarModal(modal);

    const focoAnterior = activador instanceof HTMLElement
        ? activador
        : document.activeElement;

    focosAnteriores.set(modal, focoAnterior);

    modal.returnValue = '';

    try {
        modal.showModal();
    } catch {
        focosAnteriores.delete(modal);

        return false;
    }

    const focoInicial = modal.querySelector(
        '[data-modal-autofocus]'
    );

    if (
        focoInicial instanceof HTMLElement
        && !focoInicial.matches(':disabled')
    ) {
        focoInicial.focus();
    }

    modal.dispatchEvent(
        new CustomEvent('joysys:modal-abierto', {
            bubbles: true,
            detail: {
                id: modal.id,
            },
        })
    );

    return true;
}

/* 4. CIERRE */

function cerrarModal(id, resultado = '') {
    const modal = obtenerModal(id);

    if (!modal || !modal.open) {
        return false;
    }

    modal.close(String(resultado));

    return true;
}

/* 5. EVENTOS DE INTERACCION */

document.addEventListener('click', (evento) => {
    if (!(evento.target instanceof Element)) {
        return;
    }

    const control = evento.target.closest(
        '[data-modal-open], [data-modal-close]'
    );

    if (!control) {
        return;
    }

    if (
        control.matches(':disabled')
        || control.getAttribute('aria-disabled') === 'true'
    ) {
        return;
    }

    evento.preventDefault();

    if (control.hasAttribute('data-modal-open')) {
        abrirModal(
            control.dataset.modalOpen,
            control
        );

        return;
    }

    const modal = control.closest(
        'dialog[data-js-modal]'
    );

    if (modal) {
        cerrarModal(
            modal.id,
            control.dataset.modalResult ?? ''
        );
    }
});

/* 6. INTERFAZ PUBLICA */

export const modales = Object.freeze({
    abrir: abrirModal,
    cerrar: cerrarModal,

    estaAbierto(id) {
        return obtenerModal(id)?.open === true;
    },
});

window.JoySysModales = modales;