<?php

namespace App\Http\Requests\Catalogos;

use Illuminate\Foundation\Http\FormRequest;

class GuardarMetalRequest extends FormRequest
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

    public function rules(): array
    {
        return [
            'nombre' => [
                'required',
                'string',
                'max:100',
            ],

            'simbolo' => [
                'nullable',
                'string',
                'max:10',
            ],

            'descripcion' => [
                'nullable',
                'string',
                function ($attribute, $value, $fail) {
                    if (is_string($value) && strlen($value) > 65535) {
                        $fail('La descripcion no puede superar 65535 bytes.');
                    }
                },
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
            'nombre.required' =>
                'El nombre del metal es obligatorio.',

            'nombre.string' =>
                'El nombre debe ser una cadena de texto.',

            'nombre.max' =>
                'El nombre no puede superar los 100 caracteres.',

            'simbolo.string' =>
                'El símbolo debe ser una cadena de texto.',

            'simbolo.max' =>
                'El símbolo no puede superar los 10 caracteres.',

            'descripcion.string' =>
                'La descripción debe ser una cadena de texto.',

            'descripcion.max' =>
                'La descripción es demasiado extensa.',

            'estado.prohibited' =>
                'El estado debe modificarse mediante la operación correspondiente.',

            'deleted_at.prohibited' =>
                'No está permitido eliminar metales mediante este formulario.',
        ];
    }
}