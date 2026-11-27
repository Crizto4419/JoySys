@php
    $identidadProtegida = $metal->exists
        && app(\App\Services\Catalogos\MetalService::class)->tieneReferencias($metal);
@endphp

<div class="grid grid-cols-1 gap-4">
    @if ($identidadProtegida)
        <x-ui.alert variant="info">
            El nombre y el símbolo están protegidos porque el metal tiene referencias.
            Puedes modificar su descripción.
        </x-ui.alert>

        <input type="hidden" name="nombre" value="{{ $metal->nombre }}">
        <input type="hidden" name="simbolo" value="{{ $metal->simbolo }}">

        <p><strong>Nombre:</strong> {{ $metal->nombre }}</p>
        <p><strong>Símbolo:</strong> {{ $metal->simbolo ?? '—' }}</p>
    @else
        <x-forms.input
            name="nombre"
            label="Nombre"
            :value="$metal->nombre"
            maxlength="100"
            required
        />

        <x-forms.input
            name="simbolo"
            label="Símbolo"
            :value="$metal->simbolo"
            maxlength="10"
        />
    @endif

    <x-forms.textarea
        name="descripcion"
        label="Descripción"
        :value="$metal->descripcion"
        :rows="4"
    />

    <div class="flex flex-wrap gap-2">
        <x-ui.button type="submit">Guardar metal</x-ui.button>

        <x-ui.button variant="secondary" :href="route('catalogos.metales.index')">
            Cancelar
        </x-ui.button>
    </div>
</div>
