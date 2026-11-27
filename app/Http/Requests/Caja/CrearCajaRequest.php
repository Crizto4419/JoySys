<?php

namespace App\Http\Requests\Caja;

use App\Services\Caja\CajaService;
use Illuminate\Foundation\Http\FormRequest;

class CrearCajaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('joysys.caja.administrar') ?? false;
    }

    public function rules(): array
    {
        return CajaService::reglasDatos();
    }

    public function attributes(): array
    {
        return [
            'codigo' => 'código',
            'nombre' => 'nombre',
        ];
    }
}
