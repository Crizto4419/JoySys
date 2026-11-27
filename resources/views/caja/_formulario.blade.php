<div class="grid gap-5 md:grid-cols-2">
    <x-forms.input
        name="codigo"
        label="Código de caja"
        :value="$caja?->codigo"
        maxlength="40"
        :readonly="$caja !== null && $caja->sesiones()->exists()"
        required
    />

    <x-forms.input
        name="nombre"
        label="Nombre de caja"
        :value="$caja?->nombre"
        maxlength="100"
        required
    />
</div>

<div class="mt-5">
    <x-ui.alert variant="info">
        La moneda de operación es MXN.
        La sucursal se determina automáticamente.
    </x-ui.alert>
</div>
