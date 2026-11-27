<div class="flex flex-wrap items-center gap-2">
    <x-ui.button
        :variant="request()->routeIs('caja.index', 'caja.show', 'caja.edit', 'caja.create') ? 'primary' : 'secondary'"
        :href="route('caja.index')"
    >
        Cajas
    </x-ui.button>

    <x-ui.button
        :variant="request()->routeIs('caja.sesion.actual') ? 'primary' : 'secondary'"
        :href="route('caja.sesion.actual')"
    >
        Mi sesión
    </x-ui.button>

    @can('joysys.permiso', 'caja.abrir')
        <x-ui.button
            :variant="request()->routeIs('caja.sesion.create') ? 'primary' : 'secondary'"
            :href="route('caja.sesion.create')"
        >
            Abrir caja
        </x-ui.button>
    @endcan
</div>
