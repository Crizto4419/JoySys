<?php

namespace App\Http\Requests\Catalogos;

use App\Services\Catalogos\ProductoService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConsultaProductosRequest extends FormRequest
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
        return [
            'buscar' => ['nullable', 'string', 'max:150'],
            'estado' => [
                'nullable',
                'string',
                Rule::in(['activo', 'inactivo']),
            ],
            'tipo' => [
                'nullable',
                'string',
                Rule::in(ProductoService::TIPOS),
            ],
            'categoria_id' => ['nullable', 'integer', 'min:1'],
            'metal_id' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->url();
    }
}
