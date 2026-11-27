<?php

namespace App\Http\Requests\Catalogos;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConsultaCatalogosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            'joysys.permiso',
            'catalogos.ver'
        ) ?? false;
    }

    public function validationData(): array
    {
        return $this->query();
    }

    public function rules(): array
    {
        $reglas = [
            'buscar' => [
                'nullable',
                'string',
                'max:150',
            ],

            'page' => [
                'nullable',
                'integer',
                'min:1',
            ],
        ];

        if ($this->routeIs('catalogos.categorias.index')) {
            $reglas['estado'] = [
                'nullable',
                'string',
                Rule::in([
                    'activa',
                    'inactiva',
                ]),
            ];

            $reglas['categoria_padre_id'] = [
                'nullable',

                function (
                    string $attribute,
                    mixed $value,
                    Closure $fail
                ): void {
                    if ($value === 'principal') {
                        return;
                    }

                    $idValido = filter_var(is_string($value) || is_int($value) ? $value : null,
                        FILTER_VALIDATE_INT,
                        [
                            'options' => [
                                'min_range' => 1,
                            ],
                        ]
                    );

                    if ($idValido === false) {
                        $fail(
                            'La categoría padre seleccionada no es válida.'
                        );
                    }
                },
            ];
        }

        if ($this->routeIs('catalogos.metales.index')) {
            $reglas['estado'] = [
                'nullable',
                'string',
                Rule::in([
                    'activo',
                    'inactivo',
                ]),
            ];
        }

        if ($this->routeIs('catalogos.purezas.index')) {
            $reglas['metal_id'] = [
                'nullable',
                'integer',
                'min:1',
            ];
        }

        return $reglas;
    }

    public function messages(): array
    {
        return [
            'buscar.string' =>
                'El término de búsqueda debe ser una cadena de texto.',

            'buscar.max' =>
                'La búsqueda no puede superar los 150 caracteres.',

            'estado.string' =>
                'El estado seleccionado no es válido.',

            'estado.in' =>
                'El estado seleccionado no es válido para este catálogo.',

            'metal_id.integer' =>
                'El metal seleccionado no es válido.',

            'metal_id.min' =>
                'El metal seleccionado no es válido.',

            'page.integer' =>
                'El número de página debe ser un número entero.',

            'page.min' =>
                'El número de página debe ser mayor o igual a uno.',
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->url();
    }
}