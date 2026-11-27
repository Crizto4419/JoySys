<?php

namespace App\Http\Requests\Inventario;

use App\Services\Inventario\IncidenciaService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportarIncidenciaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            'joysys.permiso',
            'incidencias.reportar'
        ) ?? false;
    }

    public function rules(): array
    {
        return [
            'producto_id' => [
                'required',
                'integer',
                'min:1',
                'exists:productos,id',
            ],
            'tipo_incidencia' => [
                'required',
                'string',
                Rule::in(
                    array_keys(IncidenciaService::TIPOS)
                ),
            ],
            'descripcion' => [
                'required',
                'string',
                'max:16000',
            ],
            'sucursal_id' => ['prohibited'],
            'usuario_reporta_id' => ['prohibited'],
            'estado' => ['prohibited'],
            'created_at' => ['prohibited'],
            'updated_at' => ['prohibited'],
        ];
    }
}
