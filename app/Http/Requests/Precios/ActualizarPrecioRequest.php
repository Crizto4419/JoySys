<?php

namespace App\Http\Requests\Precios;

use Illuminate\Foundation\Http\FormRequest;

class ActualizarPrecioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            'joysys.permiso',
            'precios.modificar'
        ) ?? false;
    }

    public function rules(): array
    {
        return [
            'precio' => [
                'required',
                'regex:/^\d{1,8}(?:\.\d{1,4})?$/D',
                'numeric',
                'gt:0',
            ],
            'moneda' => ['prohibited'],
            'vigente_desde' => ['prohibited'],
            'usuario_id' => ['prohibited'],
            'precio_anterior' => ['prohibited'],
            'precio_base' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'precio.required' => 'Introduce el nuevo precio.',
            'precio.regex' => 'Utiliza hasta ocho enteros y cuatro decimales.',
            'precio.numeric' => 'El precio debe ser numerico.',
            'precio.gt' => 'El precio debe ser mayor que cero.',
            'prohibited' => 'El campo :attribute no puede modificarse.',
        ];
    }
}
