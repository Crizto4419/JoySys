<?php

namespace App\Http\Requests\Catalogos;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CambiarEstadoProductoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            'joysys.permiso',
            'catalogos.cambiar_estado'
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
        ];
    }

    public function messages(): array
    {
        return [
            'estado.required' => 'Selecciona un estado.',
            'estado.in' => 'El estado seleccionado no es valido.',
        ];
    }
}
