<?php

namespace App\Http\Requests\Clientes;

class ActualizarClienteRequest extends CrearClienteRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            'joysys.permiso',
            'clientes.editar'
        ) ?? false;
    }
}
