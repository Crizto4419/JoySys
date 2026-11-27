<div class="grid grid-cols-1 gap-4">
    <x-forms.input
        name="nombre"
        label="Nombre"
        :value="$categoria->nombre"
        maxlength="120"
        required
    />

    <x-forms.select
        name="categoria_padre_id"
        label="Categoría padre"
        placeholder="Sin categoría padre"
        :options="$padresDisponibles->pluck('nombre', 'id')->all()"
        :value="$categoria->categoria_padre_id"
    />

    <x-forms.textarea
        name="descripcion"
        label="Descripción"
        :value="$categoria->descripcion"
        :rows="4"
    />

    <div class="flex flex-wrap gap-2">
        <x-ui.button type="submit">Guardar categoría</x-ui.button>

        <x-ui.button variant="secondary" :href="route('catalogos.categorias.index')">
            Cancelar
        </x-ui.button>
    </div>
</div>
