<?php

namespace App\Http\Requests\Catalogos;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarCategoriaRequest extends FormRequest
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
                'max:120',
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

            'categoria_padre_id' => [
                'nullable',
                'integer',
                'min:1',
                Rule::exists('categorias', 'id')
                    ->whereNull('deleted_at')
                    ->where('estado', 'activa'),
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
                'El nombre de la categoría es obligatorio.',

            'nombre.string' =>
                'El nombre debe ser una cadena de texto.',

            'nombre.max' =>
                'El nombre no puede superar los 120 caracteres.',

            'descripcion.string' =>
                'La descripción debe ser una cadena de texto.',

            'descripcion.max' =>
                'La descripción es demasiado extensa.',

            'categoria_padre_id.integer' =>
                'La categoría padre seleccionada no es válida.',

            'categoria_padre_id.min' =>
                'La categoría padre seleccionada no es válida.',

            'categoria_padre_id.exists' =>
                'La categoría padre seleccionada no existe o no está activa.',

            'estado.prohibited' =>
                'El estado debe modificarse mediante la operación correspondiente.',

            'deleted_at.prohibited' =>
                'No está permitido eliminar categorías mediante este formulario.',
        ];
    }
}