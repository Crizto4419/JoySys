<?php

namespace App\Http\Requests\Catalogos;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CambiarEstadoMetalRequest extends FormRequest
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
                Rule::in([
                    'activo',
                    'inactivo',
                ]),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'estado.required' =>
                'Debes seleccionar un estado.',

            'estado.string' =>
                'El estado seleccionado no es válido.',

            'estado.in' =>
                'El estado debe ser activo o inactivo.',
        ];
    }
}