<?php

namespace Tests\Feature;

use App\Models\Permiso;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\PermisoSeeder;
use Database\Seeders\RolPermisoSeeder;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AutorizacionTest extends TestCase
{
    private bool $transaccionIniciada = false;

    private const PERMISOS_EMPLEADO = [
        'dashboard.ver',
        'catalogos.ver',
        'inventario.ver',
        'incidencias.ver',
        'incidencias.reportar',
        'precios.ver',
        'clientes.ver',
        'clientes.crear',
        'clientes.editar',
        'ventas.ver',
        'ventas.crear',
        'ventas.descuento_basico',
        'pagos.ver',
        'pagos.registrar',
        'caja.ver',
        'caja.abrir',
        'caja.cerrar',
    ];

    private const PERMISOS_ADICIONALES_ENCARGADO = [
        'sucursales.ver',
        'ventas.descuento_superior',
        'caja.registrar_gasto',
        'caja.registrar_retiro',
        'reportes.ver',
    ];

    private const PERMISOS_EXCLUSIVOS_ADMINISTRADOR = [
        'usuarios.ver',
        'usuarios.crear',
        'usuarios.editar',
        'usuarios.cambiar_estado',
        'usuarios.restablecer_password',
        'roles.ver',
        'roles.asignar',
        'permisos.gestionar',
        'sucursales.editar',
        'sucursales.asignar_empleados',
        'catalogos.crear',
        'catalogos.editar',
        'catalogos.cambiar_estado',
        'inventario.registrar_entrada',
        'inventario.registrar_salida',
        'inventario.ajustar',
        'incidencias.resolver',
        'precios.modificar',
        'precios.ver_historial',
        'clientes.desactivar',
        'caja.autorizar_compensacion',
        'configuracion.gestionar',
    ];

    private const MODULOS_EMPLEADO = [
        'Catálogos',
        'Inventario',
        'Incidencias',
        'Precios',
        'Clientes',
        'Ventas',
        'Pagos',
        'Caja',
    ];

    private const MODULOS_ADICIONALES_ENCARGADO = [
        'Reportes',
        'Sucursales',
    ];

    private const MODULOS_EXCLUSIVOS_ADMINISTRADOR = [
        'Usuarios',
        'Roles y permisos',
        'Configuración',
    ];

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

        $this->assertSame(
            0,
            DB::table('rol_permiso')->count()
        );

        $this->seed([
            RoleSeeder::class,
            PermisoSeeder::class,
            RolPermisoSeeder::class,
        ]);
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

    private function obtenerPermiso(
        string $clave
    ): Permiso {
        return Permiso::query()
            ->where('clave', $clave)
            ->firstOrFail();
    }

    private function permisosEncargado(): array
    {
        return array_merge(
            self::PERMISOS_EMPLEADO,
            self::PERMISOS_ADICIONALES_ENCARGADO
        );
    }

    private function permisosAdministrador(): array
    {
        return array_merge(
            $this->permisosEncargado(),
            self::PERMISOS_EXCLUSIVOS_ADMINISTRADOR
        );
    }

    private function verificarMatriz(
        User $usuario,
        array $permitidos
    ): void {
        $todos = $this->permisosAdministrador();

        $this->assertCount(44, $todos);

        $this->assertEqualsCanonicalizing(
            $todos,
            Permiso::query()->pluck('clave')->all()
        );

        $this->assertEqualsCanonicalizing(
            $permitidos,
            $usuario->role->permisos()
                ->pluck('permisos.clave')
                ->all()
        );

        foreach ($todos as $clave) {
            $this->assertSame(
                in_array($clave, $permitidos, true),
                $usuario->can('joysys.permiso', $clave),
                "Permiso incorrecto: {$clave}"
            );
        }
    }

    private function verificarModulos(
        User $usuario,
        array $visibles,
        array $ocultos
    ): void {
        $respuesta = $this->actingAs($usuario)
            ->get(route('dashboard'));

        $respuesta->assertOk();

        $respuesta->assertSee('Módulos autorizados');

        foreach ($visibles as $modulo) {
            $respuesta->assertSeeText($modulo);
        }

        foreach ($ocultos as $modulo) {
            $respuesta->assertDontSeeText($modulo);
        }

        $this->assertSame(
            count($visibles),
            substr_count(
                $respuesta->getContent(),
                'class="module-title"'
            )
        );
    }

    public function test_entorno_de_pruebas_aislado(): void
    {
        $this->assertTrue($this->transaccionIniciada);

        $this->assertSame(3, Role::count());

        $this->assertSame(44, Permiso::count());

        $this->assertSame(
            83,
            DB::table('rol_permiso')->count()
        );
    }

    public function test_matriz_completa_administrador(): void
    {
        $usuario = $this->crearUsuario('administrador');

        $permisos = $this->permisosAdministrador();

        $this->assertCount(44, $permisos);

        $this->verificarMatriz($usuario, $permisos);
    }

    public function test_matriz_completa_encargado(): void
    {
        $usuario = $this->crearUsuario('encargado');

        $permisos = $this->permisosEncargado();

        $this->assertCount(22, $permisos);

        $this->verificarMatriz($usuario, $permisos);
    }

    public function test_matriz_completa_empleado(): void
    {
        $usuario = $this->crearUsuario('empleado');

        $this->assertCount(
            17,
            self::PERMISOS_EMPLEADO
        );

        $this->verificarMatriz(
            $usuario,
            self::PERMISOS_EMPLEADO
        );
    }

    public function test_rechaza_permisos_inexistentes(): void
    {
        foreach ([
            'administrador',
            'encargado',
            'empleado',
        ] as $slug) {
            $usuario = $this->crearUsuario($slug);

            $this->assertFalse(
                $usuario->can(
                    'joysys.permiso',
                    'permiso.inexistente'
                )
            );
        }
    }

    public function test_rechaza_permisos_vacios(): void
    {
        $usuario = $this->crearUsuario('administrador');

        $this->assertFalse(
            $usuario->can('joysys.permiso', '')
        );

        $this->assertFalse(
            $usuario->can('joysys.permiso', '   ')
        );
    }

    public function test_rechaza_usuario_inactivo(): void
    {
        $usuario = $this->crearUsuario();

        $this->assertTrue(
            $usuario->can('joysys.permiso', 'dashboard.ver')
        );

        User::query()
            ->whereKey($usuario->id)
            ->update(['estado' => 'inactivo']);

        $this->assertFalse(
            $usuario->can('joysys.permiso', 'dashboard.ver')
        );
    }

    public function test_rechaza_rol_inactivo(): void
    {
        $usuario = $this->crearUsuario();

        $this->assertTrue(
            $usuario->can('joysys.permiso', 'dashboard.ver')
        );

        Role::query()
            ->whereKey($usuario->role_id)
            ->update(['estado' => 'inactivo']);

        $this->assertFalse(
            $usuario->can('joysys.permiso', 'dashboard.ver')
        );
    }

    public function test_rechaza_usuario_eliminado(): void
    {
        $usuario = $this->crearUsuario();

        $this->assertTrue(
            $usuario->can('joysys.permiso', 'dashboard.ver')
        );

        User::query()
            ->whereKey($usuario->id)
            ->delete();

        $this->assertFalse(
            $usuario->can('joysys.permiso', 'dashboard.ver')
        );
    }

    public function test_rechaza_usuario_bloqueado(): void
    {
        $usuario = $this->crearUsuario();

        $usuario->forceFill([
            'intentos_fallidos' => 5,
            'bloqueado_hasta' => now()->addMinutes(15),
        ])->save();

        $this->assertFalse(
            $usuario->can('joysys.permiso', 'dashboard.ver')
        );
    }

    public function test_autoriza_despues_del_bloqueo(): void
    {
        $usuario = $this->crearUsuario();

        $this->freezeTime();

        $usuario->forceFill([
            'intentos_fallidos' => 5,
            'bloqueado_hasta' => now()->addMinutes(15),
        ])->save();

        $this->assertFalse(
            $usuario->can('joysys.permiso', 'dashboard.ver')
        );

        $this->travel(16)->minutes();

        $this->assertTrue(
            $usuario->can('joysys.permiso', 'dashboard.ver')
        );
    }

    public function test_revoca_permisos_sin_cerrar_sesion(): void
    {
        $usuario = $this->crearUsuario('administrador');

        $this->actingAs($usuario);

        $this->get(route('dashboard'))->assertOk();

        $permiso = $this->obtenerPermiso('dashboard.ver');

        $usuario->role->permisos()->detach($permiso->id);

        $this->assertFalse(
            $usuario->can('joysys.permiso', 'dashboard.ver')
        );

        $this->get(route('dashboard'))->assertForbidden();

        $this->assertAuthenticatedAs($usuario);
    }

    public function test_reconoce_permisos_recien_asignados(): void
    {
        $usuario = $this->crearUsuario('empleado');

        $this->actingAs($usuario);

        $this->assertFalse(
            $usuario->can('joysys.permiso', 'reportes.ver')
        );

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertDontSeeText('Reportes');

        $permiso = $this->obtenerPermiso('reportes.ver');

        $usuario->role->permisos()->attach(
            $permiso->id,
            [
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $this->assertTrue(
            $usuario->can('joysys.permiso', 'reportes.ver')
        );

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSeeText('Reportes');

        $this->assertAuthenticatedAs($usuario);
    }

    public function test_redirige_invitados_al_login(): void
    {
        $this->get(route('dashboard'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_acceso_autorizado_al_dashboard(): void
    {
        foreach ([
            'administrador',
            'encargado',
            'empleado',
        ] as $slug) {
            $usuario = $this->crearUsuario($slug);

            $this->actingAs($usuario)
                ->get(route('dashboard'))
                ->assertOk()
                ->assertSeeText('Bienvenido');

            $this->assertAuthenticatedAs($usuario);
        }
    }

    public function test_respuesta_403_sin_permiso(): void
    {
        $usuario = $this->crearUsuario();

        $permiso = $this->obtenerPermiso('dashboard.ver');

        $usuario->role->permisos()->detach($permiso->id);

        $this->actingAs($usuario)
            ->get(route('dashboard'))
            ->assertForbidden()
            ->assertSeeText('Acceso denegado')
            ->assertSeeText('Cerrar sesión')
            ->assertDontSeeText('Volver al dashboard');

        $this->assertAuthenticatedAs($usuario);
    }

    public function test_cierra_sesion_sin_permiso_dashboard(): void
    {
        $usuario = $this->crearUsuario();

        $permiso = $this->obtenerPermiso('dashboard.ver');

        $usuario->role->permisos()->detach($permiso->id);

        $this->actingAs($usuario)
            ->get(route('dashboard'))
            ->assertForbidden();

        $this->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_modulos_visibles_administrador(): void
    {
        $usuario = $this->crearUsuario('administrador');

        $visibles = array_merge(
            self::MODULOS_EMPLEADO,
            self::MODULOS_ADICIONALES_ENCARGADO,
            self::MODULOS_EXCLUSIVOS_ADMINISTRADOR
        );

        $this->assertCount(13, $visibles);

        $this->verificarModulos(
            $usuario,
            $visibles,
            []
        );
    }

    public function test_modulos_visibles_encargado(): void
    {
        $usuario = $this->crearUsuario('encargado');

        $visibles = array_merge(
            self::MODULOS_EMPLEADO,
            self::MODULOS_ADICIONALES_ENCARGADO
        );

        $this->assertCount(10, $visibles);

        $this->verificarModulos(
            $usuario,
            $visibles,
            self::MODULOS_EXCLUSIVOS_ADMINISTRADOR
        );
    }

    public function test_modulos_visibles_empleado(): void
    {
        $usuario = $this->crearUsuario('empleado');

        $ocultos = array_merge(
            self::MODULOS_ADICIONALES_ENCARGADO,
            self::MODULOS_EXCLUSIVOS_ADMINISTRADOR
        );

        $this->assertCount(8, self::MODULOS_EMPLEADO);

        $this->verificarModulos(
            $usuario,
            self::MODULOS_EMPLEADO,
            $ocultos
        );
    }
}