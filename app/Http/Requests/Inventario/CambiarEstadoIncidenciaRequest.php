<?php

namespace App\Http\Requests\Inventario;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CambiarEstadoIncidenciaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            'joysys.permiso',
            'incidencias.resolver'
        ) ?? false;
    }

    public function rules(): array
    {
        return [
            'estado' => [
                'required',
                'string',
                Rule::in([
                    'en_revision',
                    'resuelta',
                ]),
            ],
            'producto_id' => ['prohibited'],
            'sucursal_id' => ['prohibited'],
            'usuario_reporta_id' => ['prohibited'],
            'descripcion' => ['prohibited'],
            'tipo_incidencia' => ['prohibited'],
            'created_at' => ['prohibited'],
            'updated_at' => ['prohibited'],
        ];
    }
}
