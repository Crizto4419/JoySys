<?php

namespace App\Http\Requests\Caja;

use App\Services\Auth\ContextoSucursalService;
use App\Services\Caja\SesionCajaService;
use Illuminate\Foundation\Http\FormRequest;

class AbrirSesionCajaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $usuario = $this->user();

        return $usuario !== null
            && $usuario->can('joysys.permiso', 'caja.abrir')
            && app(ContextoSucursalService::class)
                ->obtenerSucursalId($usuario) !== null;
    }

    public function rules(): array
    {
        return SesionCajaService::reglasApertura();
    }

    public function attributes(): array
    {
        return [
            'caja_id' => 'caja',
            'fondo_inicial' => 'fondo inicial',
        ];
    }
}
