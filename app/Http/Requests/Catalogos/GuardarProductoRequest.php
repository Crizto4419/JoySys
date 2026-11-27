<?php

namespace App\Http\Requests\Catalogos;

use App\Services\Catalogos\ProductoService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarProductoRequest extends FormRequest
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
            'categoria_id' => [
                'required',
                'integer',
                'min:1',
                Rule::exists('categorias', 'id'),
            ],
            'proveedor_id' => [
                'nullable',
                'integer',
                'min:1',
                Rule::exists('proveedores', 'id'),
            ],
            'metal_id' => [
                'nullable',
                'integer',
                'min:1',
                Rule::exists('metales', 'id'),
            ],
            'pureza_id' => [
                'nullable',
                'integer',
                'min:1',
                Rule::exists('purezas', 'id'),
            ],
            'codigo' => [
                'required',
                'string',
                'max:60',
            ],
            'nombre' => [
                'required',
                'string',
                'max:150',
            ],
            'descripcion' => [
                'nullable',
                'string',
                function ($attribute, $value, $fail) {
                    if (is_string($value) && strlen($value) > 65535) {
                        $fail('La descripcion supera el limite permitido.');
                    }
                },
            ],
            'tipo' => [
                'required',
                'string',
                Rule::in(ProductoService::TIPOS),
            ],
            'stock_minimo' => [
                'nullable',
                'regex:/^\d{1,8}(?:\.\d{1,4})?$/D',
            ],
            'precio_base' => $this->isMethod('POST')
                ? [
                    'required',
                    'regex:/^\d{1,8}(?:\.\d{1,4})?$/D',
                    'numeric',
                    'gt:0',
                ]
                : ['prohibited'],
            'estado' => ['prohibited'],
            'deleted_at' => ['prohibited'],
            'precio' => ['prohibited'],
            'moneda' => ['prohibited'],
            'vigente_desde' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'integer' => 'El campo :attribute debe ser un entero.',
            'exists' => 'El registro seleccionado no esta disponible.',
            'max' => 'El campo :attribute supera el limite permitido.',
            'regex' => 'Utiliza hasta ocho enteros y cuatro decimales.',
            'precio_base.gt' => 'El precio inicial debe ser mayor que cero.',
            'tipo.in' => 'El tipo de producto no es valido.',
            'prohibited' => 'El campo :attribute no puede modificarse aqui.',
        ];
    }
}
