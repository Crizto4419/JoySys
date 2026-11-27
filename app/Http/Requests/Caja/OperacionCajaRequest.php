<?php

namespace App\Http\Requests\Caja;

use App\Services\Caja\MovimientoCajaService;
use App\Services\Caja\SesionCajaService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class OperacionCajaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $permiso = match ($this->route()->getName()) {
            'caja.movimientos.gasto' => 'caja.registrar_gasto',
            'caja.movimientos.retiro' => 'caja.registrar_retiro',
            'caja.movimientos.compensacion' =>
                'caja.autorizar_compensacion',
            'caja.sesion.cerrar' => 'caja.cerrar',
            default => null,
        };

        return $permiso !== null
            && $this->user() !== null
            && $this->user()->can('joysys.permiso', $permiso);
    }

    public function rules(): array
    {
        return match ($this->route()->getName()) {
            'caja.sesion.cerrar' =>
                SesionCajaService::reglasCierre(),

            'caja.movimientos.compensacion' =>
                array_replace(
                    MovimientoCajaService::reglasSalida(),
                    [
                        'observaciones' => [
                            'required', 'string', 'max:5000',
                        ],
                    ]
                ),

            default => array_merge(
                MovimientoCajaService::reglasSalida(),
                [
                    'sesion_id' => [
                        'required', 'integer', 'min:1',
                    ],
                ]
            ),
        };
    }

    protected function passedValidation(): void
    {
        $permitidos = array_merge(
            array_keys($this->rules()),
            ['_token', '_method']
        );

        foreach (array_keys($this->all()) as $campo) {
            if (!in_array($campo, $permitidos, true)) {
                throw ValidationException::withMessages([
                    $campo => 'Este campo no puede proporcionarse.',
                ]);
            }
        }
    }
}