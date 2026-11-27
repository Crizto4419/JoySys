<?php

namespace App\Http\Requests\Inventario;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OperacionInventarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        $permiso = match ($this->route()->getName()) {
            'inventario.entradas.store' =>
                'inventario.registrar_entrada',

            'inventario.salidas.store' =>
                'inventario.registrar_salida',

            'inventario.ajustes.store' =>
                'inventario.ajustar',

            default => null,
        };

        return $permiso !== null &&
            ($this->user()?->can(
                'joysys.permiso',
                $permiso
            ) ?? false);
    }

    public function rules(): array
    {
        $reglas = [
            'producto_id' => [
                'required',
                'integer',
                'min:1',
                'exists:productos,id',
            ],
            'sucursal_id' => ['prohibited'],
            'usuario_id' => ['prohibited'],
            'metal_id' => ['prohibited'],
            'pureza_id' => ['prohibited'],
            'tipo_movimiento' => ['prohibited'],
            'estado_inventario_id' => ['prohibited'],
            'created_at' => ['prohibited'],
            'updated_at' => ['prohibited'],
        ];

        $cantidad = [
            'nullable',
            'regex:/^[0-9]{1,8}(?:\.[0-9]{1,4})?$/D',
            'numeric',
            'gt:0',
        ];

        $observaciones = [
            'required',
            'string',
            'max:16000',
        ];

        return array_merge(
            $reglas,
            match ($this->route()->getName()) {
                'inventario.entradas.store' => [
                    'tipo_salida' => ['prohibited'],
                    'codigo_pieza' => [
                        'nullable',
                        'string',
                        'max:80',
                    ],
                    'peso_gramos' => [
                        'nullable',
                        'regex:/^[0-9]{1,5}(?:\.[0-9]{1,3})?$/D',
                        'numeric',
                        'gt:0',
                    ],
                    'cantidad' => $cantidad,
                    'observaciones' => [
                        'nullable',
                        'string',
                        'max:16000',
                    ],
                    'pieza_id' => ['prohibited'],
                    'estado_origen' => ['prohibited'],
                    'estado_destino' => ['prohibited'],
                    'sentido' => ['prohibited'],
                ],

                'inventario.salidas.store' => [
                    'tipo_salida' => [
                        'required',
                        'string',
                        Rule::in([
                            'perdida',
                            'administrativa',
                        ]),
                    ],
                    'pieza_id' => [
                        'nullable',
                        'integer',
                        'min:1',
                    ],
                    'cantidad' => $cantidad,
                    'estado_origen' => [
                        'nullable',
                        Rule::in([
                            'disponible',
                            'reparacion',
                            'danado',
                        ]),
                    ],
                    'observaciones' => $observaciones,
                    'operacion' => ['prohibited'],
                    'estado_destino' => ['prohibited'],
                    'codigo_pieza' => ['prohibited'],
                    'peso_gramos' => ['prohibited'],
                    'sentido' => ['prohibited'],
                ],

                'inventario.ajustes.store' => [
                    'tipo_salida' => ['prohibited'],
                    'operacion' => [
                        'required',
                        Rule::in([
                            'cantidad',
                            'estado',
                        ]),
                    ],
                    'pieza_id' => [
                        'nullable',
                        'integer',
                        'min:1',
                    ],
                    'cantidad' => $cantidad,
                    'sentido' => [
                        'nullable',
                        Rule::in([
                            'positivo',
                            'negativo',
                        ]),
                    ],
                    'estado_origen' => [
                        'nullable',
                        Rule::in([
                            'disponible',
                            'reparacion',
                            'danado',
                            'perdido',
                        ]),
                    ],
                    'estado_destino' => [
                        'nullable',
                        Rule::in([
                            'disponible',
                            'reparacion',
                            'danado',
                        ]),
                    ],
                    'observaciones' => $observaciones,
                    'codigo_pieza' => ['prohibited'],
                    'peso_gramos' => ['prohibited'],
                ],

                default => [],
            }
        );
    }

    public function messages(): array
    {
        return [
            'required' => 'Este campo es obligatorio.',
            'integer' => 'Selecciona un registro valido.',
            'exists' => 'El registro seleccionado no existe.',
            'regex' => 'El formato numerico no es valido.',
            'gt' => 'El valor debe ser mayor que cero.',
            'in' => 'La opcion seleccionada no es valida.',
            'prohibited' => 'Este campo no puede enviarse.',
            'max' => 'El valor supera la longitud permitida.',
        ];
    }
}
