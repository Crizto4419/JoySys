<?php

namespace App\Http\Requests\Caja;

use App\Services\Caja\CajaService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CambiarEstadoCajaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('joysys.caja.administrar') ?? false;
    }

    public function rules(): array
    {
        return [
            'estado' => ['required', Rule::in(CajaService::ESTADOS)],
            'codigo' => ['prohibited'],
            'nombre' => ['prohibited'],
            'sucursal_id' => ['prohibited'],
            'moneda' => ['prohibited'],
            'id' => ['prohibited'],
            'created_at' => ['prohibited'],
            'updated_at' => ['prohibited'],
        ];
    }
}
