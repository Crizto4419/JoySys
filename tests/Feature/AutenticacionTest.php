<?php

namespace Tests\Feature;

use App\Models\Permiso;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AutenticacionTest extends TestCase
{
    private const PASSWORD = 'JoySys-Test-Only-2026!';

    private bool $transaccionIniciada = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('testing', app()->environment());

        $this->assertSame(
            'mysql',
            config('database.default')
        );

        $this->assertSame(
            'joysys_testing',
            config('database.connections.mysql.database')
        );

        $this->assertSame(
            'joysys_test_user',
            config('database.connections.mysql.username')
        );

        $this->assertSame(
            'joysys_testing',
            DB::selectOne('SELECT DATABASE() AS nombre')->nombre
        );

        $this->assertSame(
            'joysys_test_user@localhost',
            DB::selectOne('SELECT CURRENT_USER() AS usuario')->usuario
        );

        $this->assertSame('array', config('cache.default'));
        $this->assertSame('array', config('session.driver'));

        DB::beginTransaction();

        $this->transaccionIniciada = true;
    }

    protected function tearDown(): void
    {
        try {
            if ($this->transaccionIniciada) {
                DB::rollBack();
            }
        } finally {
            parent::tearDown();
        }
    }

    private function crearUsuario(): User
    {
        $rol = Role::firstOrCreate(
            ['slug' => 'empleado'],
            [
                'nombre' => 'Empleado',
                'descripcion' => 'Rol de pruebas',
                'estado' => 'activo',
            ]
        );

        $permiso = Permiso::firstOrCreate(
            ['clave' => 'dashboard.ver'],
            [
                'nombre' => 'Consultar dashboard',
                'modulo' => 'dashboard',
                'descripcion' => null,
            ]
        );

        $rol->permisos()->syncWithoutDetaching([
            $permiso->id => [
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        return User::factory()->create([
            'role_id' => $rol->id,
            'password' => self::PASSWORD,
            'estado' => 'activo',
            'intentos_fallidos' => 0,
            'bloqueado_hasta' => null,
        ]);
    }

    private function intentarAcceso(
        User $usuario,
        string $password
    ) {
        return $this->post(route('login.store'), [
            'username' => $usuario->username,
            'password' => $password,
        ]);
    }

    public function test_entorno_de_pruebas_aislado(): void
    {
        $this->assertTrue($this->transaccionIniciada);
    }

    public function test_registra_intentos_fallidos(): void
    {
        $usuario = $this->crearUsuario();

        $this->intentarAcceso(
            $usuario,
            'PasswordIncorrecto'
        )->assertSessionHasErrors('username');

        $this->assertGuest();

        $this->assertSame(
            1,
            $usuario->fresh()->intentos_fallidos
        );
    }

    public function test_acceso_correcto_reinicia_intentos(): void
    {
        $usuario = $this->crearUsuario();

        $this->intentarAcceso(
            $usuario,
            'PasswordIncorrecto'
        );

        $this->intentarAcceso(
            $usuario,
            self::PASSWORD
        )->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($usuario);

        $usuario->refresh();

        $this->assertSame(0, $usuario->intentos_fallidos);
        $this->assertNull($usuario->bloqueado_hasta);
        $this->assertNotNull($usuario->ultimo_acceso);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSeeText('Bienvenido');
    }

    public function test_bloquea_al_quinto_intento(): void
    {
        $usuario = $this->crearUsuario();

        $this->freezeTime();

        for ($i = 0; $i < 5; $i++) {
            $this->intentarAcceso(
                $usuario,
                'PasswordIncorrecto'
            )->assertSessionHasErrors('username');
        }

        $usuario->refresh();

        $this->assertSame(5, $usuario->intentos_fallidos);
        $this->assertSame('activo', $usuario->estado);
        $this->assertNotNull($usuario->bloqueado_hasta);

        $this->assertSame(
            now()->addMinutes(15)->toDateTimeString(),
            $usuario->bloqueado_hasta->toDateTimeString()
        );

        $this->assertGuest();
    }

    public function test_bloqueo_impide_acceso_sin_extenderse(): void
    {
        $usuario = $this->crearUsuario();

        $usuario->forceFill([
            'intentos_fallidos' => 5,
            'bloqueado_hasta' => now()->addMinutes(15),
        ])->save();

        $bloqueoOriginal = $usuario->fresh()
            ->bloqueado_hasta
            ->toDateTimeString();

        $this->travel(1)->minutes();

        $this->intentarAcceso(
            $usuario,
            self::PASSWORD
        )->assertSessionHasErrors('username');

        $this->assertGuest();

        $this->assertSame(
            $bloqueoOriginal,
            $usuario->fresh()
                ->bloqueado_hasta
                ->toDateTimeString()
        );
    }

    public function test_permite_acceso_despues_del_bloqueo(): void
    {
        $usuario = $this->crearUsuario();

        $usuario->forceFill([
            'intentos_fallidos' => 5,
            'bloqueado_hasta' => now()->addMinutes(15),
        ])->save();

        $this->travel(16)->minutes();

        $this->intentarAcceso(
            $usuario,
            self::PASSWORD
        )->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($usuario);

        $usuario->refresh();

        $this->assertSame(0, $usuario->intentos_fallidos);
        $this->assertNull($usuario->bloqueado_hasta);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSeeText('Bienvenido');
    }

    public function test_rechaza_usuario_inactivo(): void
    {
        $usuario = $this->crearUsuario();

        $usuario->forceFill([
            'estado' => 'inactivo',
        ])->save();

        $this->intentarAcceso(
            $usuario,
            self::PASSWORD
        )->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_rechaza_rol_inactivo(): void
    {
        $usuario = $this->crearUsuario();

        $usuario->role->update([
            'estado' => 'inactivo',
        ]);

        $this->intentarAcceso(
            $usuario,
            self::PASSWORD
        )->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_rechaza_usuario_eliminado(): void
    {
        $usuario = $this->crearUsuario();

        $usuario->delete();

        $this->intentarAcceso(
            $usuario,
            self::PASSWORD
        )->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_limita_solicitudes_por_ip(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->post(route('login.store'), [
                'username' => 'usuario_inexistente',
                'password' => 'PasswordIncorrecto',
            ])->assertSessionHasErrors('username');
        }

        $this->post(route('login.store'), [
            'username' => 'usuario_inexistente',
            'password' => 'PasswordIncorrecto',
        ])->assertSessionHasErrors([
            'username' =>
                'Demasiadas solicitudes. Intenta nuevamente en unos momentos.',
        ]);

        $this->assertGuest();
    }
}