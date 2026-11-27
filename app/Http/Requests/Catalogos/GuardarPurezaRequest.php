<?php

namespace App\Http\Requests\Catalogos;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarPurezaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $permiso = $this->isMethod('POST')
            ? 'catalogos.crear'
            : 'catalogos.editar';

        return $this->user()?->can(
            'joysys.permiso',
            $permiso
        ) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $codigo = $this->input('codigo');

        if (is_string($codigo)) {
            $this->merge([
                'codigo' => mb_strtoupper(
                    trim($codigo),
                    'UTF-8'
                ),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'metal_id' => [
                'required',
                'integer',
                'min:1',
                Rule::exists('metales', 'id'),
            ],

            'codigo' => [
                'required',
                'string',
                'max:40',
            ],

            'nombre' => [
                'required',
                'string',
                'max:100',
            ],

            'estado' => [
                'prohibited',
            ],

            'deleted_at' => [
                'prohibited',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'metal_id.required' =>
                'Debes seleccionar un metal.',

            'metal_id.integer' =>
                'El metal seleccionado no es válido.',

            'metal_id.min' =>
                'El metal seleccionado no es válido.',

            'metal_id.exists' =>
                'El metal seleccionado no existe.',

            'codigo.required' =>
                'El código de la pureza es obligatorio.',

            'codigo.string' =>
                'El código debe ser una cadena de texto.',

            'codigo.max' =>
                'El código no puede superar los 40 caracteres.',

            'nombre.required' =>
                'El nombre de la pureza es obligatorio.',

            'nombre.string' =>
                'El nombre debe ser una cadena de texto.',

            'nombre.max' =>
                'El nombre no puede superar los 100 caracteres.',

            'estado.prohibited' =>
                'Las purezas no tienen un estado independiente.',

            'deleted_at.prohibited' =>
                'No está permitido eliminar purezas mediante este formulario.',
        ];
    }
}