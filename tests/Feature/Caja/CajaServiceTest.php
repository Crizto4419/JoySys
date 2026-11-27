<?php

namespace Tests\Feature\Caja;

use App\Models\Caja;
use App\Models\SesionCaja;
use App\Models\User;
use App\Services\Caja\CajaService;
use App\Services\Caja\SesionCajaService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CajaServiceTest extends TestCase
{
    private CajaService $cajas;

    private SesionCajaService $sesiones;

    private User $admin;

    private int $sucursal;

    private int $otraSucursal;

    private int $secuencia = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cajas = app(CajaService::class);
        $this->sesiones = app(SesionCajaService::class);

        $this->sucursal = $this->crearSucursal('CAJA-SUC-01');
        $this->otraSucursal = $this->crearSucursal('CAJA-SUC-02');

        $this->admin = $this->crearUsuario(
            'administrador',
            $this->sucursal
        );
    }

    private function crearSucursal(string $codigo): int
    {
        return DB::table('sucursales')->insertGetId([
            'codigo' => $codigo,
            'nombre' => $codigo,
            'estado' => 'activa',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function crearUsuario(
        string $slug,
        ?int $sucursal,
        array $permisos = [
            'dashboard.ver',
            'caja.ver',
            'caja.abrir',
            'caja.cerrar',
        ]
    ): User {
        $rol = DB::table('roles')
            ->where('slug', $slug)
            ->value('id');

        if ($rol === null) {
            $rol = DB::table('roles')->insertGetId([
                'nombre' => ucfirst($slug),
                'slug' => $slug,
                'estado' => 'activo',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach ($permisos as $clave) {
            $permiso = DB::table('permisos')
                ->where('clave', $clave)
                ->value('id');

            if ($permiso === null) {
                $permiso = DB::table('permisos')->insertGetId([
                    'clave' => $clave,
                    'nombre' => $clave,
                    'modulo' => explode('.', $clave)[0],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('rol_permiso')->insertOrIgnore([
                'rol_id' => $rol,
                'permiso_id' => $permiso,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $usuario = User::factory()->create([
            'role_id' => $rol,
            'estado' => 'activo',
        ]);

        if ($sucursal !== null) {
            DB::table('empleado_sucursal')->insert([
                'usuario_id' => $usuario->id,
                'sucursal_id' => $sucursal,
                'es_principal' => true,
                'fecha_inicio' => now()->subDay()->toDateString(),
                'fecha_fin' => null,
                'estado' => 'activo',
                'asignado_por' => $usuario->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $usuario;
    }

    private function datos(array $cambios = []): array
    {
        return array_replace([
            'codigo' => 'CAJ-TEST-'.(++$this->secuencia),
            'nombre' => 'Caja de pruebas',
        ], $cambios);
    }

    private function caja(array $cambios = []): Caja
    {
        return $this->cajas->crear(
            $this->datos($cambios),
            $this->admin
        );
    }

    private function cajaDirecta(
        int $sucursal,
        array $cambios = []
    ): Caja {
        return Caja::query()->create(array_replace([
            'sucursal_id' => $sucursal,
            'codigo' => 'CAJ-DIR-'.(++$this->secuencia),
            'nombre' => 'Caja directa',
            'moneda' => 'MXN',
            'estado' => 'activa',
        ], $cambios));
    }

    private function abrir(
        Caja $caja,
        ?User $usuario = null,
        mixed $fondo = '500.0000'
    ): SesionCaja {
        return $this->sesiones->abrir([
            'caja_id' => $caja->id,
            'fondo_inicial' => $fondo,
        ], $usuario ?? $this->admin);
    }

    private function cerrarFixture(SesionCaja $sesion): void
    {
        DB::table('sesiones_caja')
            ->where('id', $sesion->id)
            ->update([
                'estado' => 'cerrada',
                'cerrada_por' => $sesion->usuario_id,
                'saldo_esperado' => $sesion->fondo_inicial,
                'efectivo_contado' => $sesion->fondo_inicial,
                'diferencia' => '0.0000',
                'fecha_cierre' => now(),
                'updated_at' => now(),
            ]);
    }

    private function errorValidacion(
        string $campo,
        callable $operacion
    ): void {
        try {
            $operacion();

            $this->fail('Se esperaba un error en '.$campo);
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                $campo,
                $exception->errors()
            );
        }
    }

    private function errorMysql(
        int $codigo,
        callable $operacion
    ): void {
        try {
            DB::transaction($operacion);

            $this->fail('Se esperaba una restriccion de MySQL.');
        } catch (QueryException $exception) {
            $this->assertSame(
                $codigo,
                (int) ($exception->errorInfo[1] ?? 0)
            );
        }
    }

    private function datosSesion(
        Caja $caja,
        User $usuario,
        array $cambios = []
    ): array {
        return array_replace([
            'caja_id' => $caja->id,
            'sucursal_id' => $caja->sucursal_id,
            'usuario_id' => $usuario->id,
            'fondo_inicial' => '100.0000',
            'fecha_apertura' => now(),
            'estado' => 'abierta',
            'created_at' => now(),
            'updated_at' => now(),
        ], $cambios);
    }

    // ADMINISTRACION: C01-C12

    public function test_c01_registro_caja(): void
    {
        $caja = $this->caja();

        $this->assertSame('activa', $caja->estado);
        $this->assertSame('MXN', $caja->moneda);
        $this->assertEquals($this->sucursal, $caja->sucursal_id);
        $this->assertDatabaseHas('cajas', [
            'id' => $caja->id,
            'codigo' => $caja->codigo,
        ]);
    }

    public function test_c02_normalizacion(): void
    {
        $caja = $this->caja([
            'codigo' => '  CAJ-ESP  ',
            'nombre' => '  Caja principal  ',
        ]);

        $this->assertSame('CAJ-ESP', $caja->codigo);
        $this->assertSame('Caja principal', $caja->nombre);
    }

    public function test_c03_campos_obligatorios(): void
    {
        foreach (['codigo', 'nombre'] as $campo) {
            $this->errorValidacion(
                $campo,
                fn () => $this->caja([$campo => '   '])
            );
        }

        $this->assertDatabaseCount('cajas', 0);
    }

    public function test_c04_longitudes(): void
    {
        $this->errorValidacion(
            'codigo',
            fn () => $this->caja([
                'codigo' => str_repeat('A', 41),
            ])
        );

        $this->errorValidacion(
            'nombre',
            fn () => $this->caja([
                'nombre' => str_repeat('A', 101),
            ])
        );

        $caja = $this->caja([
            'codigo' => str_repeat('A', 40),
            'nombre' => str_repeat('B', 100),
        ]);

        $this->assertSame(40, strlen($caja->codigo));
        $this->assertSame(100, strlen($caja->nombre));
    }

    public function test_c05_codigo_duplicado(): void
    {
        $caja = $this->caja();

        $this->errorValidacion(
            'codigo',
            fn () => $this->caja(['codigo' => $caja->codigo])
        );

        $this->assertDatabaseCount('cajas', 1);
    }

    public function test_c06_codigo_igual_otra_sucursal(): void
    {
        $caja = $this->caja();

        $otro = $this->crearUsuario(
            'administrador',
            $this->otraSucursal
        );

        $segunda = $this->cajas->crear([
            'codigo' => $caja->codigo,
            'nombre' => 'Otra caja',
        ], $otro);

        $this->assertSame($caja->codigo, $segunda->codigo);
        $this->assertNotEquals($caja->sucursal_id, $segunda->sucursal_id);
    }

    public function test_c07_campos_protegidos(): void
    {
        foreach ([
            'sucursal_id' => $this->otraSucursal,
            'moneda' => 'USD',
            'estado' => 'inactiva',
            'id' => 999,
        ] as $campo => $valor) {
            $this->errorValidacion(
                $campo,
                fn () => $this->caja([$campo => $valor])
            );
        }

        $this->assertDatabaseCount('cajas', 0);
    }

    public function test_c08_edicion_sin_historial(): void
    {
        $caja = $this->caja();

        $actual = $this->cajas->actualizar($caja, [
            'codigo' => 'CAJ-NUEVO',
            'nombre' => 'Nombre actualizado',
        ], $this->admin);

        $this->assertSame('CAJ-NUEVO', $actual->codigo);
        $this->assertSame('Nombre actualizado', $actual->nombre);
        $this->assertSame('MXN', $actual->moneda);
    }

    public function test_c09_edicion_codigo_duplicado(): void
    {
        $primera = $this->caja();
        $segunda = $this->caja();

        $this->errorValidacion(
            'codigo',
            fn () => $this->cajas->actualizar($segunda, [
                'codigo' => $primera->codigo,
                'nombre' => $segunda->nombre,
            ], $this->admin)
        );
    }

    public function test_c10_estados_validos_e_invalidos(): void
    {
        $caja = $this->caja();

        foreach (['inactiva', 'mantenimiento', 'activa'] as $estado) {
            $actual = $this->cajas->cambiarEstado(
                $caja,
                $estado,
                $this->admin
            );

            $this->assertSame($estado, $actual->estado);
        }

        $this->errorValidacion(
            'estado',
            fn () => $this->cajas->cambiarEstado(
                $caja,
                'eliminada',
                $this->admin
            )
        );
    }

    public function test_c11_protege_caja_con_sesion_abierta(): void
    {
        $caja = $this->caja();
        $this->abrir($caja);

        $this->errorValidacion(
            'caja',
            fn () => $this->cajas->actualizar($caja, [
                'codigo' => $caja->codigo,
                'nombre' => 'Cambio prohibido',
            ], $this->admin)
        );

        foreach (['inactiva', 'mantenimiento'] as $estado) {
            $this->errorValidacion(
                'caja',
                fn () => $this->cajas->cambiarEstado(
                    $caja,
                    $estado,
                    $this->admin
                )
            );
        }

        $this->assertSame('activa', $caja->fresh()->estado);
    }

    public function test_c12_protege_codigo_historico(): void
    {
        $caja = $this->caja();
        $this->cerrarFixture($this->abrir($caja));

        $this->errorValidacion(
            'codigo',
            fn () => $this->cajas->actualizar($caja, [
                'codigo' => 'CAJ-CAMBIO',
                'nombre' => $caja->nombre,
            ], $this->admin)
        );

        $actual = $this->cajas->actualizar($caja, [
            'codigo' => $caja->codigo,
            'nombre' => 'Nombre permitido',
        ], $this->admin);

        $this->assertSame('Nombre permitido', $actual->nombre);

        $this->assertSame(
            'inactiva',
            $this->cajas->cambiarEstado(
                $caja,
                'inactiva',
                $this->admin
            )->estado
        );
    }

    // APERTURA: C13-C24

    public function test_c13_apertura_correcta(): void
    {
        $caja = $this->caja();
        $sesion = $this->abrir($caja);

        $this->assertSame('abierta', $sesion->estado);
        $this->assertSame('500.0000', $sesion->fondo_inicial);
        $this->assertEquals($caja->id, $sesion->caja_id);
        $this->assertEquals($this->admin->id, $sesion->usuario_id);
        $this->assertEquals($this->sucursal, $sesion->sucursal_id);
        $this->assertNotNull($sesion->fecha_apertura);

        foreach ([
            'cerrada_por',
            'saldo_esperado',
            'efectivo_contado',
            'diferencia',
            'fecha_cierre',
        ] as $campo) {
            $this->assertNull($sesion->{$campo});
        }

        $this->assertDatabaseCount('movimientos_caja', 0);
    }

    public function test_c14_fondo_cero(): void
    {
        $this->assertSame(
            '0.0000',
            $this->abrir($this->caja(), fondo: '0')->fondo_inicial
        );
    }

    public function test_c15_precision_decimal(): void
    {
        $this->assertSame(
            '123.4567',
            $this->abrir(
                $this->caja(),
                fondo: '123.4567'
            )->fondo_inicial
        );

        $this->assertSame(
            '12.3000',
            SesionCajaService::normalizarFondo('00012.3')
        );
    }

    public function test_c16_fondo_maximo(): void
    {
        $this->assertSame(
            '99999999.9999',
            $this->abrir(
                $this->caja(),
                fondo: '99999999.9999'
            )->fondo_inicial
        );
    }

    public function test_c17_rechaza_fondos_invalidos(): void
    {
        $caja = $this->caja();

        foreach ([
            '-1', '100000000', '1.12345', '1e3',
            '1,000', 'abc', '', null, [], true, 1.5,
        ] as $importe) {
            $this->errorValidacion(
                'fondo_inicial',
                fn () => $this->abrir($caja, fondo: $importe)
            );
        }

        $this->assertDatabaseCount('sesiones_caja', 0);
    }

    public function test_c18_caja_inactiva(): void
    {
        $caja = $this->cajaDirecta(
            $this->sucursal,
            ['estado' => 'inactiva']
        );

        $this->errorValidacion(
            'caja_id',
            fn () => $this->abrir($caja)
        );
    }

    public function test_c19_caja_mantenimiento(): void
    {
        $caja = $this->cajaDirecta(
            $this->sucursal,
            ['estado' => 'mantenimiento']
        );

        $this->errorValidacion(
            'caja_id',
            fn () => $this->abrir($caja)
        );
    }

    public function test_c20_moneda_no_admitida(): void
    {
        $caja = $this->cajaDirecta(
            $this->sucursal,
            ['moneda' => 'USD']
        );

        $this->errorValidacion(
            'caja_id',
            fn () => $this->abrir($caja)
        );
    }

    public function test_c21_caja_ocupada(): void
    {
        $caja = $this->caja();
        $this->abrir($caja);

        $otro = $this->crearUsuario(
            'empleado',
            $this->sucursal
        );

        $this->errorValidacion(
            'caja_id',
            fn () => $this->abrir($caja, $otro)
        );

        $this->assertDatabaseCount('sesiones_caja', 1);
    }

    public function test_c22_empleado_con_sesion_abierta(): void
    {
        $this->abrir($this->caja());
        $otra = $this->caja();

        $this->errorValidacion(
            'caja_id',
            fn () => $this->abrir($otra)
        );

        $this->assertDatabaseCount('sesiones_caja', 1);
    }

    public function test_c23_nueva_sesion_despues_de_cierre(): void
    {
        $caja = $this->caja();
        $primera = $this->abrir($caja);

        $this->cerrarFixture($primera);

        $segunda = $this->abrir($caja);

        $this->assertNotEquals($primera->id, $segunda->id);
        $this->assertSame('cerrada', $primera->fresh()->estado);
        $this->assertSame('abierta', $segunda->estado);
        $this->assertDatabaseCount('sesiones_caja', 2);
    }

    public function test_c24_consulta_sesion_propia(): void
    {
        $sesion = $this->abrir($this->caja());

        $otro = $this->crearUsuario(
            'empleado',
            $this->sucursal
        );

        $this->assertEquals(
            $sesion->id,
            $this->sesiones->obtenerAbierta($this->admin)->id
        );

        $this->assertNull(
            $this->sesiones->obtenerAbierta($otro)
        );
    }

    // PERMISOS Y AISLAMIENTO: C25-C32

    public function test_c25_encargado_no_administra(): void
    {
        $usuario = $this->crearUsuario(
            'encargado',
            $this->sucursal
        );

        $this->expectException(AuthorizationException::class);

        $this->cajas->crear($this->datos(), $usuario);
    }

    public function test_c26_empleado_no_administra(): void
    {
        $usuario = $this->crearUsuario(
            'empleado',
            $this->sucursal
        );

        $this->expectException(AuthorizationException::class);

        $this->cajas->crear($this->datos(), $usuario);
    }

    public function test_c27_roles_operativos_pueden_abrir(): void
    {
        foreach (['encargado', 'empleado'] as $slug) {
            $usuario = $this->crearUsuario(
                $slug,
                $this->sucursal
            );

            $sesion = $this->abrir($this->caja(), $usuario);

            $this->assertEquals($usuario->id, $sesion->usuario_id);
            $this->assertSame('abierta', $sesion->estado);
        }
    }

    public function test_c28_sin_permiso_apertura(): void
    {
        $usuario = $this->crearUsuario(
            'empleado',
            $this->sucursal,
            ['dashboard.ver', 'caja.ver']
        );

        $caja = $this->caja();

        $this->expectException(AuthorizationException::class);

        $this->abrir($caja, $usuario);
    }

    public function test_c29_sin_sucursal_operativa(): void
    {
        $usuario = $this->crearUsuario('empleado', null);

        $this->actingAs($usuario)
            ->get(route('caja.index'))
            ->assertForbidden();

        $this->expectException(AuthorizationException::class);

        $this->cajas->listar($usuario);
    }

    public function test_c30_listado_aislado_por_sucursal(): void
    {
        $propia = $this->caja();

        $this->cajaDirecta($this->otraSucursal);

        $resultado = $this->cajas->listar($this->admin);

        $this->assertSame(1, $resultado->total());
        $this->assertEquals($propia->id, $resultado->items()[0]->id);
    }

    public function test_c31_rechaza_apertura_otra_sucursal(): void
    {
        $caja = $this->cajaDirecta($this->otraSucursal);

        $this->expectException(ModelNotFoundException::class);

        $this->abrir($caja);
    }

    public function test_c32_rechaza_modificacion_otra_sucursal(): void
    {
        $caja = $this->cajaDirecta($this->otraSucursal);

        $this->expectException(ModelNotFoundException::class);

        $this->cajas->actualizar($caja, [
            'codigo' => $caja->codigo,
            'nombre' => 'Modificacion externa',
        ], $this->admin);
    }

    // INTEGRIDAD: C33-C40

    public function test_c33_mysql_codigo_unico(): void
    {
        $caja = $this->caja();

        $this->errorMysql(
            1062,
            fn () => $this->cajaDirecta(
                $this->sucursal,
                ['codigo' => $caja->codigo]
            )
        );
    }

    public function test_c34_mysql_una_sesion_por_caja(): void
    {
        $caja = $this->caja();
        $this->abrir($caja);

        $otro = $this->crearUsuario(
            'empleado',
            $this->sucursal
        );

        $this->errorMysql(
            1062,
            fn () => DB::table('sesiones_caja')->insert(
                $this->datosSesion($caja, $otro)
            )
        );
    }

    public function test_c35_mysql_una_sesion_por_empleado(): void
    {
        $this->abrir($this->caja());

        $otra = $this->caja();

        $this->errorMysql(
            1062,
            fn () => DB::table('sesiones_caja')->insert(
                $this->datosSesion($otra, $this->admin)
            )
        );
    }

    public function test_c36_columnas_generadas(): void
    {
        $caja = $this->caja();
        $sesion = $this->abrir($caja);

        $this->assertEquals(
            $caja->id,
            $sesion->caja_abierta_id
        );

        $this->assertEquals(
            $this->admin->id,
            $sesion->empleado_abierto_id
        );

        $this->cerrarFixture($sesion);

        $actual = $sesion->fresh();

        $this->assertNull($actual->caja_abierta_id);
        $this->assertNull($actual->empleado_abierto_id);
    }

    public function test_c37_mysql_fondo_no_negativo(): void
    {
        $caja = $this->caja();

        $this->errorMysql(
            3819,
            fn () => DB::table('sesiones_caja')->insert(
                $this->datosSesion($caja, $this->admin, [
                    'fondo_inicial' => '-1.0000',
                ])
            )
        );
    }

    public function test_c38_mysql_coherencia_estado(): void
    {
        $caja = $this->caja();

        $this->errorMysql(
            3819,
            fn () => DB::table('sesiones_caja')->insert(
                $this->datosSesion($caja, $this->admin, [
                    'saldo_esperado' => '100.0000',
                ])
            )
        );

        $this->errorMysql(
            3819,
            fn () => DB::table('sesiones_caja')->insert(
                $this->datosSesion($caja, $this->admin, [
                    'estado' => 'cerrada',
                ])
            )
        );
    }

    public function test_c39_transaccion_revierte_operacion(): void
    {
        $antes = Caja::query()->count();

        try {
            $this->cajas->ejecutar(
                $this->admin,
                $this->sucursal,
                function () {
                    $this->cajaDirecta($this->sucursal);

                    throw new \RuntimeException('Fallo controlado');
                },
                true
            );

            $this->fail('Se esperaba el fallo controlado.');
        } catch (\RuntimeException $exception) {
            $this->assertSame(
                'Fallo controlado',
                $exception->getMessage()
            );
        }

        $this->assertSame($antes, Caja::query()->count());
    }

    public function test_c40_libera_bloqueo_exito_y_fallo(): void
    {
        $bloqueo = 'joysys:caja:'.sha1(
            DB::connection()->getDatabaseName()
        );

        $caja = $this->caja();

        $libre = fn () => (int) DB::selectOne(
            'SELECT IS_FREE_LOCK(?) AS libre',
            [$bloqueo]
        )->libre;

        $this->assertSame(1, $libre());

        $this->errorValidacion(
            'codigo',
            fn () => $this->caja(['codigo' => $caja->codigo])
        );

        $this->assertSame(1, $libre());
    }

    // INTERFAZ E INTEGRACION: C41-C50

    public function test_c41_invitados(): void
    {
        $this->get(route('caja.index'))
            ->assertRedirect(route('login'));

        $this->post(route('caja.store'), $this->datos())
            ->assertRedirect(route('login'));

        $this->get(route('caja.sesion.actual'))
            ->assertRedirect(route('login'));
    }

    public function test_c42_pantallas_administrador(): void
    {
        $caja = $this->caja();

        $this->actingAs($this->admin);

        foreach ([
            route('caja.index'),
            route('caja.create'),
            route('caja.show', $caja),
            route('caja.edit', $caja),
            route('caja.sesion.create'),
            route('caja.sesion.actual'),
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_c43_registro_http(): void
    {
        $datos = $this->datos();

        $respuesta = $this->actingAs($this->admin)
            ->post(route('caja.store'), $datos);

        $caja = Caja::query()
            ->where('codigo', $datos['codigo'])
            ->firstOrFail();

        $respuesta->assertRedirect(route('caja.show', $caja));

        $this->assertSame('activa', $caja->estado);
        $this->assertSame('MXN', $caja->moneda);
    }

    public function test_c44_edicion_y_estado_http(): void
    {
        $caja = $this->caja();

        $this->actingAs($this->admin)
            ->put(route('caja.update', $caja), [
                'codigo' => $caja->codigo,
                'nombre' => 'Caja editada HTTP',
            ])
            ->assertRedirect(route('caja.show', $caja));

        $this->assertSame(
            'Caja editada HTTP',
            $caja->fresh()->nombre
        );

        $this->patch(route('caja.estado', $caja), [
            'estado' => 'mantenimiento',
        ])->assertRedirect(route('caja.show', $caja));

        $this->assertSame(
            'mantenimiento',
            $caja->fresh()->estado
        );
    }

    public function test_c45_apertura_http(): void
    {
        $caja = $this->caja();

        $this->actingAs($this->admin)
            ->post(route('caja.sesion.store'), [
                'caja_id' => $caja->id,
                'fondo_inicial' => '250.1250',
            ])
            ->assertRedirect(route('caja.sesion.actual'));

        $this->assertDatabaseHas('sesiones_caja', [
            'caja_id' => $caja->id,
            'usuario_id' => $this->admin->id,
            'fondo_inicial' => '250.1250',
            'estado' => 'abierta',
        ]);

        $this->get(route('caja.sesion.actual'))->assertOk();

        $this->get(route('caja.sesion.create'))
            ->assertRedirect(route('caja.sesion.actual'));
    }

    public function test_c46_manipulacion_http(): void
    {
        $caja = $this->caja();

        $this->actingAs($this->admin)
            ->postJson(route('caja.store'), $this->datos([
                'sucursal_id' => $this->otraSucursal,
                'moneda' => 'USD',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'sucursal_id',
                'moneda',
            ]);

        $this->postJson(route('caja.sesion.store'), [
            'caja_id' => $caja->id,
            'fondo_inicial' => '100',
            'usuario_id' => 999,
            'sucursal_id' => $this->otraSucursal,
            'saldo_esperado' => '100',
            'estado' => 'cerrada',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'usuario_id',
                'sucursal_id',
                'saldo_esperado',
                'estado',
            ]);

        $this->assertDatabaseCount('sesiones_caja', 0);
    }

    public function test_c47_restricciones_http_roles(): void
    {
        $caja = $this->caja();

        foreach (['encargado', 'empleado'] as $slug) {
            $usuario = $this->crearUsuario(
                $slug,
                $this->sucursal
            );

            $this->actingAs($usuario);

            $this->get(route('caja.index'))->assertOk();
            $this->get(route('caja.show', $caja))->assertOk();
            $this->get(route('caja.sesion.create'))->assertOk();

            $this->get(route('caja.create'))->assertForbidden();

            $this->post(route('caja.store'), $this->datos())
                ->assertForbidden();

            $this->get(route('caja.edit', $caja))
                ->assertForbidden();

            $this->put(route('caja.update', $caja), $this->datos())
                ->assertForbidden();

            $this->patch(route('caja.estado', $caja), [
                'estado' => 'inactiva',
            ])->assertForbidden();
        }
    }

    public function test_c48_busqueda_filtros_y_disponibilidad(): void
    {
        $primera = $this->caja([
            'codigo' => 'CAJ-%-01',
            'nombre' => 'Principal',
        ]);

        $segunda = $this->caja([
            'codigo' => 'CAJ-ABC-02',
            'nombre' => 'Secundaria',
        ]);

        $this->cajas->cambiarEstado(
            $segunda,
            'mantenimiento',
            $this->admin
        );

        $tercera = $this->caja();

        $this->abrir($tercera);

        $this->cajaDirecta($this->otraSucursal);

        $this->assertSame(
            1,
            $this->cajas->listar($this->admin, [
                'buscar' => '%',
            ])->total()
        );

        $this->assertSame(
            1,
            $this->cajas->listar($this->admin, [
                'buscar' => 'Principal',
            ])->total()
        );

        $this->assertSame(
            1,
            $this->cajas->listar($this->admin, [
                'estado' => 'mantenimiento',
            ])->total()
        );

        $disponibles = $this->cajas->disponibles($this->admin);

        $this->assertCount(1, $disponibles);
        $this->assertEquals($primera->id, $disponibles->first()->id);

        $this->errorValidacion(
            'estado',
            fn () => $this->cajas->listar($this->admin, [
                'estado' => 'incorrecto',
            ])
        );
    }

    public function test_c49_rutas_sin_eliminacion(): void
    {
        $rutas = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($ruta) => str_starts_with(
                $ruta->getName() ?? '',
                'caja.'
            ));

        $this->assertCount(10, $rutas);

        foreach ($rutas as $ruta) {
            $this->assertNotContains('DELETE', $ruta->methods());
            $this->assertContains(
                'sucursal.activa',
                $ruta->gatherMiddleware()
            );
        }

        $caja = $this->caja();

        $this->actingAs($this->admin)
            ->delete(route('caja.show', $caja))
            ->assertStatus(405);
    }

    public function test_c50_integracion_dashboard(): void
    {
        $this->caja();

        $this->actingAs($this->admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('caja.index'))
            ->assertSee(route('caja.sesion.create'));
    }
}
