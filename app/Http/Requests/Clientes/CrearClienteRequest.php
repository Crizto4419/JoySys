<?php

namespace App\Http\Requests\Clientes;

use App\Services\Clientes\ClienteService;
use Illuminate\Foundation\Http\FormRequest;

class CrearClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            'joysys.permiso',
            'clientes.crear'
        ) ?? false;
    }

    public function rules(): array
    {
        return ClienteService::reglasDatos();
    }

    public function attributes(): array
    {
        return [
            'codigo' => 'codigo del cliente',
            'nombre' => 'nombre del cliente',
            'telefono' => 'telefono',
            'correo' => 'correo electronico',
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre del cliente es obligatorio.',
            'telefono.regex' => 'Introduce un telefono con formato valido.',
            'correo.email' => 'Introduce un correo electronico valido.',
        ];
    }
}
