<x-forms.input
    name="codigo"
    label="Código del cliente"
    :value="$cliente?->codigo"
    maxlength="50"
    help="Opcional. Puede utilizarse como referencia interna."
/>

<x-forms.input
    name="nombre"
    label="Nombre completo"
    :value="$cliente?->nombre"
    maxlength="180"
    required
/>

<x-forms.input
    name="telefono"
    label="Teléfono"
    type="tel"
    :value="$cliente?->telefono"
    maxlength="20"
    help="Opcional. Admite prefijo internacional."
/>

<x-forms.input
    name="correo"
    label="Correo electrónico"
    type="email"
    :value="$cliente?->correo"
    maxlength="150"
    help="Opcional."
/>
