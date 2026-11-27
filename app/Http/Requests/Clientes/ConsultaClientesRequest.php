<?php

namespace App\Http\Requests\Clientes;

use App\Services\Clientes\ClienteService;
use Illuminate\Foundation\Http\FormRequest;

class ConsultaClientesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            'joysys.permiso',
            'clientes.ver'
        ) ?? false;
    }

    public function validationData(): array
    {
        return $this->query();
    }

    public function rules(): array
    {
        return ClienteService::reglasConsulta();
    }

    protected function getRedirectUrl(): string
    {
        return $this->url();
    }
}
