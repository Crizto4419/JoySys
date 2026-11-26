<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('username'))) {
            $this->merge([
                'username' => trim($this->input('username')),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'username' => [
                'required',
                'string',
                'max:50',
            ],

            'password' => [
                'required',
                'string',
                'max:72',
                function ($attribute, $value, $fail) {
                    if (is_string($value) && strlen($value) > 72) {
                        $fail('La contraseña no es válida.');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'username.required' => 'Introduce tu nombre de usuario.',
            'username.string' => 'El nombre de usuario no es válido.',
            'username.max' => 'El nombre de usuario no es válido.',

            'password.required' => 'Introduce tu contraseña.',
            'password.string' => 'La contraseña no es válida.',
            'password.max' => 'La contraseña no es válida.',
        ];
    }
}