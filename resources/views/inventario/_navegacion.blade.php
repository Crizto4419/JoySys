<div class="flex flex-wrap gap-2">
    @can('joysys.permiso', 'inventario.ver')
        <x-ui.button
            :variant="request()->routeIs('inventario.index') ? 'primary' : 'secondary'"
            size="sm"
            :href="route('inventario.index')">
            Resumen
        </x-ui.button>

        <x-ui.button
            :variant="request()->routeIs('inventario.piezas') ? 'primary' : 'secondary'"
            size="sm"
            :href="route('inventario.piezas')">
            Piezas
        </x-ui.button>

        <x-ui.button
            :variant="request()->routeIs('inventario.existencias') ? 'primary' : 'secondary'"
            size="sm"
            :href="route('inventario.existencias')">
            Existencias
        </x-ui.button>

        <x-ui.button
            :variant="request()->routeIs('inventario.movimientos') ? 'primary' : 'secondary'"
            size="sm"
            :href="route('inventario.movimientos')">
            Movimientos
        </x-ui.button>
    @endcan

    @can('joysys.permiso', 'inventario.registrar_entrada')
        <x-ui.button
            :variant="request()->routeIs('inventario.entradas.*') ? 'primary' : 'secondary'"
            size="sm"
            :href="route('inventario.entradas.create')">
            Registrar entrada
        </x-ui.button>
    @endcan

    @can('joysys.permiso', 'inventario.registrar_salida')
        <x-ui.button
            :variant="request()->routeIs('inventario.salidas.*') ? 'primary' : 'secondary'"
            size="sm"
            :href="route('inventario.salidas.create')">
            Registrar salida
        </x-ui.button>
    @endcan

    @can('joysys.permiso', 'inventario.ajustar')
        <x-ui.button
            :variant="request()->routeIs('inventario.ajustes.*') ? 'primary' : 'secondary'"
            size="sm"
            :href="route('inventario.ajustes.create')">
            Ajustar inventario
        </x-ui.button>
    @endcan

    @can('joysys.permiso', 'incidencias.ver')
        <x-ui.button
            :variant="request()->routeIs('incidencias.*') ? 'primary' : 'secondary'"
            size="sm"
            :href="route('incidencias.index')">
            Incidencias
        </x-ui.button>
    @endcan
</div>
