<?php

namespace App\Http\Requests\Inventario;

use App\Services\Inventario\FiltrosInventario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConsultaInventarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        $permiso = $this->routeIs('incidencias.index')
            ? 'incidencias.ver'
            : 'inventario.ver';

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
        $pantalla = match ($this->route()->getName()) {
            'inventario.index' => 'productos',
            'inventario.piezas' => 'piezas',
            'inventario.existencias' => 'existencias',
            'inventario.movimientos' => 'movimientos',
            'incidencias.index' => 'incidencias',
            default => abort(404),
        };

        $reglas = [
            'buscar' => ['nullable', 'string', 'max:150'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];

        foreach (
            FiltrosInventario::opciones($pantalla)
            as $campo => $opciones
        ) {
            $reglas[$campo] = $campo === 'categoria_id'
                ? ['nullable', 'integer', 'min:1']
                : [
                    'nullable',
                    'string',
                    Rule::in(array_keys($opciones)),
                ];
        }

        if (in_array(
            $pantalla,
            ['movimientos', 'incidencias'],
            true
        )) {
            $reglas['fecha_desde'] = [
                'nullable',
                'date_format:Y-m-d',
            ];

            $reglas['fecha_hasta'] = [
                'nullable',
                'date_format:Y-m-d',
            ];

            if ($this->filled('fecha_desde')) {
                $reglas['fecha_hasta'][] =
                    'after_or_equal:fecha_desde';
            }
        }

        return $reglas;
    }

    protected function getRedirectUrl(): string
    {
        return $this->url();
    }
}
