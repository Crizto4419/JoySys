<?php

namespace App\Http\Requests\Precios;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConsultaPreciosRequest extends FormRequest
{
    public function authorize(): bool
    {
        $permiso = $this->routeIs('precios.historial')
            ? 'precios.ver_historial'
            : 'precios.ver';

        return $this->user()?->can(
            'joysys.permiso',
            $permiso
        ) ?? false;
    }

    public function validationData(): array
    {
        return $this->query();
    }

    public function rules(): array
    {
        if ($this->routeIs('precios.historial')) {
            return [
                'page' => ['nullable', 'integer', 'min:1'],
            ];
        }

        return [
            'buscar' => ['nullable', 'string', 'max:150'],
            'estado' => [
                'nullable',
                'string',
                Rule::in(['activo', 'inactivo']),
            ],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->url();
    }
}
