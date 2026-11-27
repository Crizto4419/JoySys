<?php

namespace Tests\Feature;

use App\Models\EmpleadoSucursal;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\Auth\ContextoSucursalService;
use Database\Seeders\PermisoSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\RolPermisoSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ContextoSucursalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame(
            0,
            DB::table('rol_permiso')->count()
        );

        $this->seed([
            RoleSeeder::class,
            PermisoSeeder::class,
            RolPermisoSeeder::class,
        ]);

        Route::middleware([
            'web',
            'auth',
            'usuario.activo',
            "can:joysys.permiso,'inventario.ver'",
            'sucursal.activa',
        ])->get(
            '/_pruebas/contexto-sucursal',
            function (Request $request) {
                $sucursal = $request->attributes->get(
                    'sucursal_operativa'
                );

                return response()->json([
                    'sucursal_id' => $request->attributes->get(
                        'sucursal_operativa_id'
                    ),
                    'sucursal_nombre' => $sucursal->nombre,
                    'sucursal_codigo' => $sucursal->codigo,
                ]);
            }
        );
    }

    private function crearUsuario(
        string $slug = 'empleado'
    ): User {
        $rol = Role::query()
            ->where('slug', $slug)
            ->firstOrFail();

        return User::factory()->create([
            'role_id' => $rol->id,
            'estado' => 'activo',
            'intentos_fallidos' => 0,
            'bloqueado_hasta' => null,
        ]);
    }

    private function crearSucursal(
        array $atributos = []
    ): Sucursal {
        return Sucursal::query()->create(
            array_merge([
                'nombre' => 'Sucursal de pruebas',
                'codigo' => 'TST-SUC-001',
                'direccion' => null,
                'telefono' => null,
                'estado' => 'activa',
            ], $atributos)
        );
    }

    private function asignarSucursal(
        User $usuario,
        Sucursal $sucursal,
        array $atributos = []
    ): EmpleadoSucursal {
        return EmpleadoSucursal::query()->create(
            array_merge([
                'usuario_id' => $usuario->id,
                'sucursal_id' => $sucursal->id,
                'es_principal' => true,
                'fecha_inicio' => now()->toDateString(),
                'fecha_fin' => null,
                'estado' => 'activo',
                'asignado_por' => $usuario->id,
            ], $atributos)
        );
    }

    private function servicio(): ContextoSucursalService
    {
        return app(ContextoSucursalService::class);
    }

    public function test_entorno_de_pruebas_aislado(): void
    {
        $this->assertTrue(
            $this->transaccionIniciada
        );
    }

    public function test_obtiene_sucursal_principal_vigente(): void
    {
        $usuario = $this->crearUsuario();
        $sucursal = $this->crearSucursal();

        $this->asignarSucursal($usuario, $sucursal);

        $resultado = $this->servicio()
            ->obtenerSucursal($usuario);

        $this->assertNotNull($resultado);

        $this->assertSame(
            $sucursal->id,
            $resultado->id
        );

        $this->assertSame(
            $sucursal->id,
            $this->servicio()->obtenerSucursalId($usuario)
        );

        $this->assertTrue(
            $this->servicio()->tieneSucursalActiva($usuario)
        );
    }

    public function test_rechaza_usuario_sin_asignacion(): void
    {
        $usuario = $this->crearUsuario();

        $this->crearSucursal();

        $this->assertNull(
            $this->servicio()->obtenerSucursal($usuario)
        );

        $this->assertNull(
            $this->servicio()->obtenerSucursalId($usuario)
        );

        $this->assertFalse(
            $this->servicio()->tieneSucursalActiva($usuario)
        );
    }

    public function test_rechaza_administrador_sin_asignacion(): void
    {
        $usuario = $this->crearUsuario('administrador');

        $this->crearSucursal();

        $this->assertNull(
            $this->servicio()->obtenerSucursal($usuario)
        );

        $this->actingAs($usuario)
            ->get('/_pruebas/contexto-sucursal')
            ->assertForbidden();
    }

    public function test_rechaza_asignacion_no_principal(): void
    {
        $usuario = $this->crearUsuario();
        $sucursal = $this->crearSucursal();

        $this->asignarSucursal(
            $usuario,
            $sucursal,
            ['es_principal' => false]
        );

        $this->assertNull(
            $this->servicio()->obtenerSucursal($usuario)
        );
    }

    public function test_rechaza_asignacion_inactiva(): void
    {
        $usuario = $this->crearUsuario();
        $sucursal = $this->crearSucursal();

        $this->asignarSucursal(
            $usuario,
            $sucursal,
            ['estado' => 'inactivo']
        );

        $this->assertNull(
            $this->servicio()->obtenerSucursal($usuario)
        );
    }

    public function test_rechaza_asignacion_con_inicio_futuro(): void
    {
        $usuario = $this->crearUsuario();
        $sucursal = $this->crearSucursal();

        $this->asignarSucursal(
            $usuario,
            $sucursal,
            [
                'fecha_inicio' => now()
                    ->addDay()
                    ->toDateString(),
            ]
        );

        $this->assertNull(
            $this->servicio()->obtenerSucursal($usuario)
        );
    }

    public function test_rechaza_asignacion_finalizada(): void
    {
        $usuario = $this->crearUsuario();
        $sucursal = $this->crearSucursal();

        $this->asignarSucursal(
            $usuario,
            $sucursal,
            [
                'fecha_inicio' => now()
                    ->subDays(10)
                    ->toDateString(),
                'fecha_fin' => now()
                    ->subDay()
                    ->toDateString(),
            ]
        );

        $this->assertNull(
            $this->servicio()->obtenerSucursal($usuario)
        );
    }

    public function test_rechaza_asignacion_con_fecha_fin_futura(): void
    {
        $usuario = $this->crearUsuario();
        $sucursal = $this->crearSucursal();

        $this->asignarSucursal(
            $usuario,
            $sucursal,
            [
                'fecha_fin' => now()
                    ->addDays(10)
                    ->toDateString(),
            ]
        );

        $this->assertNull(
            $this->servicio()->obtenerSucursal($usuario)
        );
    }

    public function test_rechaza_sucursal_inactiva(): void
    {
        $usuario = $this->crearUsuario();

        $sucursal = $this->crearSucursal([
            'estado' => 'inactiva',
        ]);

        $this->asignarSucursal($usuario, $sucursal);

        $this->assertNull(
            $this->servicio()->obtenerSucursal($usuario)
        );
    }

    public function test_rechaza_usuario_inactivo(): void
    {
        $usuario = $this->crearUsuario();
        $sucursal = $this->crearSucursal();

        $this->asignarSucursal($usuario, $sucursal);

        User::query()
            ->whereKey($usuario->id)
            ->update(['estado' => 'inactivo']);

        $this->assertNull(
            $this->servicio()->obtenerSucursal($usuario)
        );
    }

    public function test_rechaza_usuario_bloqueado(): void
    {
        $usuario = $this->crearUsuario();
        $sucursal = $this->crearSucursal();

        $this->asignarSucursal($usuario, $sucursal);

        User::query()
            ->whereKey($usuario->id)
            ->update([
                'bloqueado_hasta' => now()->addMinutes(15),
            ]);

        $this->assertNull(
            $this->servicio()->obtenerSucursal($usuario)
        );
    }

    public function test_rechaza_usuario_eliminado(): void
    {
        $usuario = $this->crearUsuario();
        $sucursal = $this->crearSucursal();

        $this->asignarSucursal($usuario, $sucursal);

        User::query()
            ->whereKey($usuario->id)
            ->delete();

        $this->assertNull(
            $this->servicio()->obtenerSucursal($usuario)
        );
    }

    public function test_rechaza_rol_inactivo(): void
    {
        $usuario = $this->crearUsuario();
        $sucursal = $this->crearSucursal();

        $this->asignarSucursal($usuario, $sucursal);

        Role::query()
            ->whereKey($usuario->role_id)
            ->update(['estado' => 'inactivo']);

        $this->assertNull(
            $this->servicio()->obtenerSucursal($usuario)
        );
    }

    public function test_valida_identificador_de_sucursal(): void
    {
        $usuario = $this->crearUsuario();

        $principal = $this->crearSucursal();

        $otra = $this->crearSucursal([
            'nombre' => 'Otra sucursal',
            'codigo' => 'TST-SUC-002',
        ]);

        $this->asignarSucursal($usuario, $principal);

        $this->asignarSucursal(
            $usuario,
            $otra,
            ['es_principal' => false]
        );

        $this->assertTrue(
            $this->servicio()->puedeOperarEnSucursal(
                $usuario,
                $principal->id
            )
        );

        $this->assertFalse(
            $this->servicio()->puedeOperarEnSucursal(
                $usuario,
                $otra->id
            )
        );

        $this->assertFalse(
            $this->servicio()->puedeOperarEnSucursal(
                $usuario,
                0
            )
        );

        $this->assertFalse(
            $this->servicio()->puedeOperarEnSucursal(
                $usuario,
                -1
            )
        );
    }

    public function test_middleware_redirige_invitados(): void
    {
        $this->get('/_pruebas/contexto-sucursal')
            ->assertRedirect(route('login'));
    }

    public function test_middleware_establece_contexto_operativo(): void
    {
        $usuario = $this->crearUsuario();
        $sucursal = $this->crearSucursal();

        $this->asignarSucursal($usuario, $sucursal);

        $this->actingAs($usuario)
            ->get('/_pruebas/contexto-sucursal')
            ->assertOk()
            ->assertExactJson([
                'sucursal_id' => $sucursal->id,
                'sucursal_nombre' => $sucursal->nombre,
                'sucursal_codigo' => $sucursal->codigo,
            ]);
    }

    public function test_middleware_rechaza_usuario_sin_sucursal(): void
    {
        $usuario = $this->crearUsuario();

        $this->actingAs($usuario)
            ->get('/_pruebas/contexto-sucursal')
            ->assertForbidden();

        $this->assertAuthenticatedAs($usuario);
    }

    public function test_middleware_ignora_contexto_enviado_por_cliente(): void
    {
        $usuario = $this->crearUsuario();

        $principal = $this->crearSucursal();

        $otra = $this->crearSucursal([
            'codigo' => 'TST-SUC-002',
        ]);

        $this->asignarSucursal($usuario, $principal);

        $url = '/_pruebas/contexto-sucursal?' . http_build_query([
            'sucursal_id' => $otra->id,
            'sucursal_operativa_id' => $otra->id,
            'sucursal_operativa' => $otra->id,
        ]);

        $this->actingAs($usuario)
            ->get($url)
            ->assertOk()
            ->assertJsonPath(
                'sucursal_id',
                $principal->id
            );
    }

    public function test_middleware_revalida_sucursal_durante_sesion(): void
    {
        $usuario = $this->crearUsuario();
        $sucursal = $this->crearSucursal();

        $this->asignarSucursal($usuario, $sucursal);

        $this->actingAs($usuario)
            ->get('/_pruebas/contexto-sucursal')
            ->assertOk();

        $sucursal->update([
            'estado' => 'inactiva',
        ]);

        $this->get('/_pruebas/contexto-sucursal')
            ->assertForbidden();

        $this->assertAuthenticatedAs($usuario);
    }

    public function test_middleware_revalida_asignacion_durante_sesion(): void
    {
        $usuario = $this->crearUsuario();
        $sucursal = $this->crearSucursal();

        $asignacion = $this->asignarSucursal(
            $usuario,
            $sucursal
        );

        $this->actingAs($usuario)
            ->get('/_pruebas/contexto-sucursal')
            ->assertOk();

        $asignacion->update([
            'estado' => 'inactivo',
            'fecha_fin' => now()->toDateString(),
        ]);

        $this->get('/_pruebas/contexto-sucursal')
            ->assertForbidden();

        $this->assertAuthenticatedAs($usuario);
    }

    public function test_middleware_respeta_permisos_del_usuario(): void
    {
        $usuario = $this->crearUsuario();
        $sucursal = $this->crearSucursal();

        $this->asignarSucursal($usuario, $sucursal);

        $permiso = $usuario->role
            ->permisos()
            ->where('permisos.clave', 'inventario.ver')
            ->firstOrFail();

        $usuario->role
            ->permisos()
            ->detach($permiso->id);

        $this->actingAs($usuario)
            ->get('/_pruebas/contexto-sucursal')
            ->assertForbidden();
    }

    public function test_dashboard_muestra_sucursal_operativa(): void
    {
        $usuario = $this->crearUsuario();

        $sucursal = $this->crearSucursal([
            'nombre' => 'Sucursal Principal de Pruebas',
        ]);

        $this->asignarSucursal($usuario, $sucursal);

        $this->actingAs($usuario)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Sucursal operativa')
            ->assertSee($sucursal->nombre)
            ->assertSee($sucursal->codigo)
            ->assertDontSee('Operaciones restringidas');
    }

    public function test_dashboard_permite_acceso_sin_sucursal(): void
    {
        $usuario = $this->crearUsuario();

        $this->actingAs($usuario)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Sin sucursal operativa')
            ->assertSee('Operaciones restringidas')
            ->assertSee('Sucursal requerida')
            ->assertSee('Cerrar sesión');
    }

    public function test_permite_cerrar_sesion_sin_sucursal(): void
    {
        $usuario = $this->crearUsuario();

        $this->actingAs($usuario)
            ->get(route('dashboard'))
            ->assertOk();

        $this->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}