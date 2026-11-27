<div class="js-stack">
    @if($identidadProtegida)
        <x-ui.alert variant="info">
            El codigo, tipo, metal y pureza estan protegidos
            porque el producto tiene referencias existentes.
        </x-ui.alert>
    @endif

    @if($categorias->isEmpty())
        <x-ui.alert variant="warning">
            Debes registrar una categoria activa antes de crear productos.
        </x-ui.alert>
    @endif

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <x-forms.input
            name="codigo"
            label="Codigo del producto"
            :value="$producto->codigo"
            :readonly="$identidadProtegida"
            maxlength="60"
            required
        />

        <x-forms.input
            name="nombre"
            label="Nombre del producto"
            :value="$producto->nombre"
            maxlength="150"
            required
        />

        <x-forms.select
            name="categoria_id"
            label="Categoria"
            placeholder="Selecciona una categoria"
            :options="$categorias->pluck('nombre', 'id')->all()"
            :value="$producto->categoria_id"
            required
        />

        @if($identidadProtegida)
            <input type="hidden" name="tipo" value="{{ $producto->tipo }}">

            <x-forms.input
                name="tipo_visible"
                label="Tipo de producto"
                :value="$tipos[$producto->tipo] ?? $producto->tipo"
                readonly
            />

            <input
                type="hidden"
                name="metal_id"
                value="{{ $producto->metal_id }}"
            >

            <x-forms.input
                name="metal_visible"
                label="Metal"
                :value="$producto->metal?->nombre ?? 'Sin metal'"
                readonly
            />

            <input
                type="hidden"
                name="pureza_id"
                value="{{ $producto->pureza_id }}"
            >

            <x-forms.input
                name="pureza_visible"
                label="Pureza"
                :value="$producto->pureza?->nombre ?? 'Sin pureza'"
                readonly
            />
        @else
            <x-forms.select
                name="tipo"
                label="Tipo de producto"
                placeholder="Selecciona un tipo"
                :options="$tipos"
                :value="$producto->tipo"
                required
            />

            <x-forms.select
                name="metal_id"
                label="Metal"
                placeholder="Sin metal asignado"
                :options="$metales->pluck('nombre', 'id')->all()"
                :value="$producto->metal_id"
            />

            <x-forms.select
                name="pureza_id"
                label="Pureza"
                placeholder="Sin pureza asignada"
                :options="$purezas->mapWithKeys(fn ($p) => [
                    $p->id => ($p->metal?->nombre ?? '') . ' - ' . $p->codigo,
                ])->all()"
                :value="$producto->pureza_id"
                help="La pureza debe corresponder al metal seleccionado."
            />
        @endif

        <x-forms.select
            name="proveedor_id"
            label="Proveedor"
            placeholder="Sin proveedor asignado"
            :options="$proveedores->pluck('nombre_comercial', 'id')->all()"
            :value="$producto->proveedor_id"
        />

        <x-forms.input
            name="stock_minimo"
            label="Stock minimo"
            type="number"
            :value="$producto->stock_minimo ?? '0.0000'"
            min="0"
            max="99999999.9999"
            step="0.0001"
            required
        />

        @if(!$producto->exists)
            <x-forms.input
                name="precio_base"
                label="Precio inicial (MXN)"
                type="number"
                min="0.0001"
                max="99999999.9999"
                step="0.0001"
                help="Genera el precio vigente y el primer registro historico."
                required
            />
        @else
            <x-forms.input
                name="precio_base_visible"
                label="Precio inicial historico (MXN)"
                :value="$producto->precio_base"
                help="Los precios vigentes se modifican desde el modulo Precios."
                readonly
            />
        @endif
    </div>

    <x-forms.textarea
        name="descripcion"
        label="Descripcion"
        :value="$producto->descripcion"
        :rows="4"
    />

    <div class="js-actions">
        <x-ui.button
            type="submit"
            :disabled="$categorias->isEmpty()"
        >
            Guardar producto
        </x-ui.button>

        <x-ui.button
            variant="secondary"
            :href="route('catalogos.productos.index')"
        >
            Cancelar
        </x-ui.button>
    </div>
</div>
