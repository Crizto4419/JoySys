<?php

namespace App\Http\Requests\Clientes;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CambiarEstadoClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            'joysys.permiso',
            'clientes.desactivar'
        ) ?? false;
    }

    public function rules(): array
    {
        return [
            'estado' => [
                'required',
                'string',
                Rule::in(['activo', 'inactivo']),
            ],
            'id' => ['prohibited'],
            'codigo' => ['prohibited'],
            'nombre' => ['prohibited'],
            'telefono' => ['prohibited'],
            'correo' => ['prohibited'],
            'activo' => ['prohibited'],
            'sucursal_id' => ['prohibited'],
            'usuario_id' => ['prohibited'],
            'created_at' => ['prohibited'],
            'updated_at' => ['prohibited'],
            'deleted_at' => ['prohibited'],
        ];
    }
}
