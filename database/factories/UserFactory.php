<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    protected static ?string $password = null;

    /**
     * Define el estado predeterminado del usuario de prueba.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'username' => 'test_' . Str::lower(
                (string) Str::ulid()
            ),

            'name' => fake()->firstName(),

            'apellidos' => fake()->lastName()
                . ' '
                . fake()->lastName(),

            'codigo_empleado' => 'TST-' . Str::upper(
                (string) Str::ulid()
            ),

            'telefono' => null,

            'email' => fake()->unique()->safeEmail(),

            'email_verified_at' => now(),

            'password' => static::$password ??= Hash::make(
                'JoySys-Test-Only-2026!'
            ),

            'role_id' => function () {
                $rol = Role::query()
                    ->where('slug', 'empleado')
                    ->where('estado', 'activo')
                    ->first();

                if (!$rol) {
                    throw new RuntimeException(
                        'El rol empleado debe existir y estar activo antes de utilizar UserFactory.'
                    );
                }

                return $rol->id;
            },

            'estado' => 'activo',

            'intentos_fallidos' => 0,

            'bloqueado_hasta' => null,

            'ultimo_acceso' => null,

            'remember_token' => Str::random(10),

            'deleted_at' => null,
        ];
    }

    /**
     * Indica que el correo del usuario no esta verificado.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
