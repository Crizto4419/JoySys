<div class="grid grid-cols-1 gap-4">
    @if ($tieneReferencias ?? false)
        <x-ui.alert variant="info">
            Esta pureza tiene referencias y no puede modificarse.
        </x-ui.alert>

        <p><strong>Metal:</strong> {{ $pureza->metal->nombre }}</p>
        <p><strong>Código:</strong> {{ $pureza->codigo }}</p>
        <p><strong>Nombre:</strong> {{ $pureza->nombre }}</p>
    @else
        <x-forms.select
            name="metal_id"
            label="Metal"
            placeholder="Selecciona un metal"
            :options="$metalesDisponibles->pluck('nombre', 'id')->all()"
            :value="$pureza->metal_id"
            required
        />

        <x-forms.input
            name="codigo"
            label="Código"
            :value="$pureza->codigo"
            maxlength="40"
            required
        />

        <x-forms.input
            name="nombre"
            label="Nombre"
            :value="$pureza->nombre"
            maxlength="100"
            required
        />

        <x-ui.button type="submit">Guardar pureza</x-ui.button>
    @endif

    <div>
        <x-ui.button variant="secondary" :href="route('catalogos.purezas.index')">
            Volver
        </x-ui.button>
    </div>
</div>
