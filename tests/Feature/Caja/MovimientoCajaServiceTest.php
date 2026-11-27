<?php

namespace Tests\Feature\Caja;

use App\Models\Caja;
use App\Models\MovimientoCaja;
use App\Models\SesionCaja;
use App\Models\User;
use App\Services\Caja\CajaService;
use App\Services\Caja\MovimientoCajaService;
use App\Services\Caja\SesionCajaService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class MovimientoCajaServiceTest extends TestCase
{
    private MovimientoCajaService $movimientos;

    private SesionCajaService $sesiones;

    private CajaService $cajas;

    private User $admin;

    private int $sucursal;

    private int $otraSucursal;

    private int $secuencia = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->movimientos = app(MovimientoCajaService::class);
        $this->sesiones = app(SesionCajaService::class);
        $this->cajas = app(CajaService::class);

        $this->sucursal = $this->crearSucursal('MOV-SUC-01');
        $this->otraSucursal = $this->crearSucursal('MOV-SUC-02');

        $this->admin = $this->crearUsuario(
            'administrador',
            $this->sucursal,
            [
                'caja.ver',
                'caja.abrir',
                'caja.cerrar',
                'caja.registrar_gasto',
                'caja.registrar_retiro',
            ]
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
        array $permisos = ['caja.ver', 'caja.abrir']
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

    private function caja(
        ?int $sucursal = null,
        array $cambios = []
    ): Caja {
        return Caja::query()->create(array_replace([
            'sucursal_id' => $sucursal ?? $this->sucursal,
            'codigo' => 'MOV-CAJ-'.(++$this->secuencia),
            'nombre' => 'Caja de movimientos',
            'moneda' => 'MXN',
            'estado' => 'activa',
        ], $cambios));
    }

    private function abrir(
        ?User $usuario = null,
        mixed $fondo = '500.0000',
        ?Caja $caja = null
    ): SesionCaja {
        $usuario ??= $this->admin;
        $caja ??= $this->caja();

        return $this->sesiones->abrir([
            'caja_id' => $caja->id,
            'fondo_inicial' => $fondo,
        ], $usuario);
    }

    private function datos(array $cambios = []): array
    {
        return array_replace([
            'monto' => '100.0000',
            'concepto' => 'Movimiento de prueba',
        ], $cambios);
    }

    private function gasto(
        array $cambios = [],
        ?User $usuario = null
    ): MovimientoCaja {
        return $this->movimientos->registrarGasto(
            $this->datos($cambios),
            $usuario ?? $this->admin
        );
    }

    private function retiro(
        array $cambios = [],
        ?User $usuario = null
    ): MovimientoCaja {
        return $this->movimientos->registrarRetiro(
            $this->datos($cambios),
            $usuario ?? $this->admin
        );
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

    private function errorAutorizacion(
        callable $operacion
    ): void {
        try {
            $operacion();

            $this->fail('Se esperaba una denegacion.');
        } catch (AuthorizationException $exception) {
            $this->assertInstanceOf(
                AuthorizationException::class,
                $exception
            );
        }
    }

    private function errorNoEncontrado(
        callable $operacion
    ): void {
        try {
            $operacion();

            $this->fail('Se esperaba un registro no encontrado.');
        } catch (ModelNotFoundException $exception) {
            $this->assertInstanceOf(
                ModelNotFoundException::class,
                $exception
            );
        }
    }

    private function saldo(SesionCaja $sesion): string
    {
        return MovimientoCajaService::calcularSaldo(
            $sesion->fondo_inicial,
            $sesion->movimientos()
                ->with('movimientoOriginal')
                ->orderBy('id')
                ->get()
        );
    }

    private function cerrarFixture(SesionCaja $sesion): void
    {
        $saldo = $this->saldo($sesion);

        DB::table('sesiones_caja')
            ->where('id', $sesion->id)
            ->update([
                'estado' => 'cerrada',
                'cerrada_por' => $sesion->usuario_id,
                'saldo_esperado' => $saldo,
                'efectivo_contado' => $saldo,
                'diferencia' => '0.0000',
                'fecha_cierre' => now(),
                'updated_at' => now(),
            ]);
    }

    // REGISTRO Y PERSISTENCIA

    public function test_m01_registro_gasto(): void
    {
        $sesion = $this->abrir();

        $movimiento = $this->gasto([
            'monto' => '125.50',
            'concepto' => 'Material de limpieza',
        ]);

        $this->assertSame('gasto', $movimiento->tipo_movimiento);
        $this->assertSame('125.5000', $movimiento->monto);
        $this->assertEquals($sesion->id, $movimiento->sesion_caja_id);
        $this->assertEquals($this->admin->id, $movimiento->usuario_id);
        $this->assertEquals($this->admin->id, $movimiento->autorizado_por);
        $this->assertNull($movimiento->detalle_pago_id);
        $this->assertNull($movimiento->movimiento_original_id);
        $this->assertNotNull($movimiento->created_at);

        $this->assertDatabaseHas('movimientos_caja', [
            'id' => $movimiento->id,
            'tipo_movimiento' => 'gasto',
            'monto' => '125.5000',
        ]);

        $this->assertSame('374.5000', $this->saldo($sesion));
    }

    public function test_m02_registro_retiro(): void
    {
        $sesion = $this->abrir();

        $movimiento = $this->retiro([
            'monto' => '200',
            'concepto' => 'Retiro de efectivo',
        ]);

        $this->assertSame('retiro', $movimiento->tipo_movimiento);
        $this->assertSame('200.0000', $movimiento->monto);
        $this->assertEquals($sesion->id, $movimiento->sesion_caja_id);
        $this->assertEquals($this->admin->id, $movimiento->autorizado_por);
        $this->assertSame('300.0000', $this->saldo($sesion));
        $this->assertDatabaseCount('movimientos_caja', 1);
    }

    public function test_m03_normalizacion_textos(): void
    {
        $this->abrir();

        $movimiento = $this->gasto([
            'concepto' => '  Compra de material  ',
            'observaciones' => '  Compra local  ',
        ]);

        $this->assertSame('Compra de material', $movimiento->concepto);
        $this->assertSame('Compra local', $movimiento->observaciones);
    }

    public function test_m04_observaciones_nulas(): void
    {
        $this->abrir();

        $primero = $this->gasto();

        $segundo = $this->retiro([
            'observaciones' => '   ',
        ]);

        $this->assertNull($primero->observaciones);
        $this->assertNull($segundo->observaciones);
    }

    public function test_m05_precision_decimal(): void
    {
        $sesion = $this->abrir(fondo: '100.0000');

        $this->gasto(['monto' => '0.0001']);
        $this->retiro(['monto' => '0.1234']);

        $this->assertSame('99.8765', $this->saldo($sesion));
    }

    public function test_m06_retiro_saldo_completo(): void
    {
        $sesion = $this->abrir(fondo: '100');

        $this->retiro(['monto' => '100']);

        $this->assertSame('0.0000', $this->saldo($sesion));

        $this->errorValidacion(
            'monto',
            fn () => $this->gasto(['monto' => '0.0001'])
        );

        $this->assertDatabaseCount('movimientos_caja', 1);
    }

    public function test_m07_efectivo_insuficiente(): void
    {
        $sesion = $this->abrir(fondo: '100');

        $this->errorValidacion(
            'monto',
            fn () => $this->gasto(['monto' => '100.0001'])
        );

        $this->errorValidacion(
            'monto',
            fn () => $this->retiro(['monto' => '101'])
        );

        $this->assertSame('100.0000', $this->saldo($sesion));
        $this->assertDatabaseCount('movimientos_caja', 0);
    }

    public function test_m08_saldo_acumulado(): void
    {
        $sesion = $this->abrir();

        $this->gasto(['monto' => '200']);
        $this->retiro(['monto' => '250']);

        $this->errorValidacion(
            'monto',
            fn () => $this->retiro(['monto' => '51'])
        );

        $this->assertSame('50.0000', $this->saldo($sesion));
        $this->assertDatabaseCount('movimientos_caja', 2);
    }

    // PERMISOS Y SESIONES

    public function test_m09_encargado_autorizado(): void
    {
        $usuario = $this->crearUsuario(
            'encargado',
            $this->sucursal,
            [
                'caja.ver',
                'caja.abrir',
                'caja.registrar_gasto',
                'caja.registrar_retiro',
            ]
        );

        $sesion = $this->abrir($usuario);

        $gasto = $this->gasto(usuario: $usuario);
        $retiro = $this->retiro(usuario: $usuario);

        $this->assertEquals($usuario->id, $gasto->autorizado_por);
        $this->assertEquals($usuario->id, $retiro->autorizado_por);
        $this->assertEquals($sesion->id, $gasto->sesion_caja_id);
        $this->assertSame('300.0000', $this->saldo($sesion));
    }

    public function test_m10_empleado_sin_permisos(): void
    {
        $usuario = $this->crearUsuario(
            'empleado',
            $this->sucursal
        );

        $this->abrir($usuario);

        $this->errorAutorizacion(
            fn () => $this->gasto(usuario: $usuario)
        );

        $this->errorAutorizacion(
            fn () => $this->retiro(usuario: $usuario)
        );

        $this->assertDatabaseCount('movimientos_caja', 0);
    }

    public function test_m11_permisos_independientes(): void
    {
        $usuario = $this->crearUsuario(
            'empleado',
            $this->sucursal,
            [
                'caja.ver',
                'caja.abrir',
                'caja.registrar_gasto',
            ]
        );

        $rol = $usuario->role_id;

        $permisoRetiro = DB::table('permisos')
            ->where('clave', 'caja.registrar_retiro')
            ->value('id');

        DB::table('rol_permiso')
            ->where('rol_id', $rol)
            ->where('permiso_id', $permisoRetiro)
            ->delete();

        $this->abrir($usuario);

        $this->assertSame(
            'gasto',
            $this->gasto(usuario: $usuario)->tipo_movimiento
        );

        $this->errorAutorizacion(
            fn () => $this->retiro(usuario: $usuario)
        );

        $this->assertDatabaseCount('movimientos_caja', 1);
    }

    public function test_m12_sin_sesion_abierta(): void
    {
        $this->errorValidacion(
            'sesion_caja',
            fn () => $this->gasto()
        );

        $this->errorValidacion(
            'sesion_caja',
            fn () => $this->retiro()
        );

        $this->assertDatabaseCount('movimientos_caja', 0);
    }

    public function test_m13_sesion_cerrada(): void
    {
        $sesion = $this->abrir();

        $this->cerrarFixture($sesion);

        $this->errorValidacion(
            'sesion_caja',
            fn () => $this->gasto()
        );

        $this->errorValidacion(
            'sesion_caja',
            fn () => $this->retiro()
        );

        $this->assertDatabaseCount('movimientos_caja', 0);
    }

    public function test_m14_sin_sucursal_operativa(): void
    {
        $usuario = $this->crearUsuario(
            'administrador',
            null
        );

        $this->errorAutorizacion(
            fn () => $this->gasto(usuario: $usuario)
        );

        $this->assertDatabaseCount('movimientos_caja', 0);
    }

    public function test_m15_no_utiliza_sesion_ajena(): void
    {
        $this->abrir();

        $otro = $this->crearUsuario(
            'administrador',
            $this->sucursal
        );

        $this->errorValidacion(
            'sesion_caja',
            fn () => $this->gasto(usuario: $otro)
        );

        $this->assertDatabaseCount('movimientos_caja', 0);
    }

    public function test_m16_caja_no_operativa(): void
    {
        $sesion = $this->abrir();

        DB::table('cajas')
            ->where('id', $sesion->caja_id)
            ->update(['estado' => 'inactiva']);

        $this->errorValidacion(
            'caja',
            fn () => $this->gasto()
        );

        $this->assertDatabaseCount('movimientos_caja', 0);
    }

    // VALIDACION Y PROTECCION

    public function test_m17_importes_invalidos(): void
    {
        $this->abrir();

        foreach ([
            '0', '-1', '1.00001', '100000000',
            '1e3', 'abc', '', null, [], true, 1.5,
        ] as $importe) {
            $this->errorValidacion(
                'monto',
                fn () => $this->gasto(['monto' => $importe])
            );
        }

        $this->assertDatabaseCount('movimientos_caja', 0);
    }

    public function test_m18_conceptos_invalidos(): void
    {
        $this->abrir();

        foreach ([
            '', '   ', null, [], str_repeat('A', 151),
        ] as $concepto) {
            $this->errorValidacion(
                'concepto',
                fn () => $this->gasto(['concepto' => $concepto])
            );
        }

        $this->assertDatabaseCount('movimientos_caja', 0);
    }

    public function test_m19_observaciones_invalidas(): void
    {
        $this->abrir();

        foreach ([[], str_repeat('A', 5001)] as $valor) {
            $this->errorValidacion(
                'observaciones',
                fn () => $this->gasto(['observaciones' => $valor])
            );
        }

        $this->assertDatabaseCount('movimientos_caja', 0);
    }

    public function test_m20_campos_protegidos(): void
    {
        $this->abrir();

        foreach ([
            'id' => 999,
            'usuario_id' => 999,
            'autorizado_por' => 999,
            'sucursal_id' => 999,
            'sesion_caja_id' => 999,
            'detalle_pago_id' => 999,
            'movimiento_original_id' => 999,
            'tipo_movimiento' => 'ingreso',
            'created_at' => '2020-01-01',
            'updated_at' => '2020-01-01',
        ] as $campo => $valor) {
            $this->errorValidacion(
                $campo,
                fn () => $this->gasto([$campo => $valor])
            );
        }

        $this->assertDatabaseCount('movimientos_caja', 0);
    }

    public function test_m21_no_modifica_datos_de_cierre(): void
    {
        $sesion = $this->abrir();

        $this->gasto();
        $this->retiro();

        $actual = $sesion->fresh();

        $this->assertSame('abierta', $actual->estado);
        $this->assertSame('500.0000', $actual->fondo_inicial);

        foreach ([
            'saldo_esperado',
            'efectivo_contado',
            'diferencia',
            'cerrada_por',
            'fecha_cierre',
        ] as $campo) {
            $this->assertNull($actual->{$campo});
        }
    }

    // CONSULTAS Y AISLAMIENTO

    public function test_m22_consulta_movimientos_propios(): void
    {
        $sesion = $this->abrir();

        $gasto = $this->gasto();
        $retiro = $this->retiro();

        $resultado = $this->movimientos->listarMovimientos(
            $sesion,
            $this->admin
        );

        $this->assertSame(2, $resultado->total());

        $this->assertEquals(
            [$retiro->id, $gasto->id],
            $resultado->getCollection()->pluck('id')->all()
        );
    }

    public function test_m23_filtro_por_tipo(): void
    {
        $sesion = $this->abrir();

        $gasto = $this->gasto();
        $this->retiro();

        $resultado = $this->movimientos->listarMovimientos(
            $sesion,
            $this->admin,
            ['tipo_movimiento' => 'gasto']
        );

        $this->assertSame(1, $resultado->total());
        $this->assertEquals($gasto->id, $resultado->items()[0]->id);
    }

    public function test_m24_filtros_invalidos(): void
    {
        $sesion = $this->abrir();

        $this->errorValidacion(
            'tipo_movimiento',
            fn () => $this->movimientos->listarMovimientos(
                $sesion,
                $this->admin,
                ['tipo_movimiento' => 'incorrecto']
            )
        );

        $this->errorValidacion(
            'page',
            fn () => $this->movimientos->listarMovimientos(
                $sesion,
                $this->admin,
                ['page' => 0]
            )
        );
    }

    public function test_m25_rechaza_consulta_sesion_ajena(): void
    {
        $sesion = $this->abrir();

        $this->gasto();

        $otro = $this->crearUsuario(
            'empleado',
            $this->sucursal
        );

        $this->errorNoEncontrado(
            fn () => $this->movimientos->listarMovimientos(
                $sesion,
                $otro
            )
        );
    }

    public function test_m26_aislamiento_otra_sucursal(): void
    {
        $otro = $this->crearUsuario(
            'administrador',
            $this->otraSucursal
        );

        $sesion = $this->abrir(
            $otro,
            caja: $this->caja($this->otraSucursal)
        );

        $this->gasto(usuario: $otro);

        $this->errorNoEncontrado(
            fn () => $this->movimientos->listarMovimientos(
                $sesion,
                $this->admin
            )
        );

        $this->errorValidacion(
            'sesion_caja',
            fn () => $this->gasto()
        );

        $this->assertDatabaseCount('movimientos_caja', 1);
    }

    public function test_m27_consulta_historial_cerrado(): void
    {
        $sesion = $this->abrir();

        $movimiento = $this->gasto();

        $this->cerrarFixture($sesion);

        $resultado = $this->movimientos->listarMovimientos(
            $sesion,
            $this->admin
        );

        $this->assertSame(1, $resultado->total());
        $this->assertEquals(
            $movimiento->id,
            $resultado->items()[0]->id
        );
    }

    public function test_m28_paginacion(): void
    {
        $sesion = $this->abrir();

        for ($i = 1; $i <= 16; $i++) {
            $this->gasto([
                'monto' => '1',
                'concepto' => 'Gasto '.$i,
            ]);
        }

        $resultado = $this->movimientos->listarMovimientos(
            $sesion,
            $this->admin
        );

        $this->assertSame(16, $resultado->total());
        $this->assertSame(15, $resultado->perPage());
        $this->assertCount(15, $resultado->items());
        $this->assertSame(2, $resultado->lastPage());
    }

    // INTEGRIDAD TRANSACCIONAL

    public function test_m29_rollback_movimiento(): void
    {
        $sesion = $this->abrir();

        try {
            DB::transaction(function () {
                $this->gasto();

                throw new RuntimeException('Fallo controlado');
            });

            $this->fail('Se esperaba el fallo controlado.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Fallo controlado',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseCount('movimientos_caja', 0);
        $this->assertSame('500.0000', $this->saldo($sesion));
    }

    public function test_m30_liberacion_bloqueo(): void
    {
        $this->abrir();

        $bloqueo = 'joysys:caja:'.sha1(
            DB::connection()->getDatabaseName()
        );

        $libre = fn () => (int) DB::selectOne(
            'SELECT IS_FREE_LOCK(?) AS libre',
            [$bloqueo]
        )->libre;

        $this->gasto();

        $this->assertSame(1, $libre());

        $this->errorValidacion(
            'monto',
            fn () => $this->retiro(['monto' => '1000'])
        );

        $this->assertSame(1, $libre());
    }

    public function test_m31_preserva_movimientos_anteriores(): void
    {
        $this->abrir();

        $primero = $this->gasto();

        $original = $primero->fresh()->getAttributes();

        $this->retiro();

        $this->errorValidacion(
            'monto',
            fn () => $this->gasto(['monto' => '1000'])
        );

        $this->assertSame(
            $original,
            $primero->fresh()->getAttributes()
        );

        $this->assertDatabaseCount('movimientos_caja', 2);
    }

    public function test_m32_sesiones_independientes(): void
    {
        $primera = $this->abrir();

        $gasto = $this->gasto();

        $this->cerrarFixture($primera);

        $segunda = $this->abrir(
            fondo: '200',
            caja: $primera->caja
        );

        $retiro = $this->retiro(['monto' => '50']);

        $this->assertEquals($primera->id, $gasto->sesion_caja_id);
        $this->assertEquals($segunda->id, $retiro->sesion_caja_id);

        $this->assertSame('400.0000', $this->saldo($primera));
        $this->assertSame('150.0000', $this->saldo($segunda));
    }


    private function concederPermisoCaja(
        \App\Models\User $usuario,
        string $clave
    ): void {
        $id = \Illuminate\Support\Facades\DB::table('permisos')
            ->where('clave', $clave)
            ->value('id');

        if ($id === null) {
            $id = \Illuminate\Support\Facades\DB::table('permisos')
                ->insertGetId([
                    'clave' => $clave,
                    'nombre' => $clave,
                    'modulo' => 'caja',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
        }

        \Illuminate\Support\Facades\DB::table('rol_permiso')
            ->insertOrIgnore([
                'rol_id' => $usuario->role_id,
                'permiso_id' => $id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }

    private function compensarPrueba(
        \App\Models\MovimientoCaja $original,
        string $monto
    ): \App\Models\MovimientoCaja {
        return $this->movimientos->registrarCompensacion(
            $original,
            [
                'monto' => $monto,
                'concepto' => 'Correccion de movimiento',
                'observaciones' => 'Efectivo reintegrado a caja',
            ],
            $this->admin
        );
    }

    private function crearPagoPrueba(
        array $partidas,
        array $cambios = []
    ): \App\Models\Pago {
        $total = '0.0000';

        foreach ($partidas as $partida) {
            $total = bcadd($total, $partida[1], 4);
        }

        $pago = \App\Models\Pago::query()->create(
            array_replace([
                'sucursal_id' => $this->sucursal,
                'referencia_tipo' => 'venta',
                'referencia_id' => 1,
                'pago_original_id' => null,
                'recibido_por' => $this->admin->getKey(),
                'monto_total' => $total,
                'moneda' => 'MXN',
                'estado' => 'confirmado',
                'fecha_pago' => now(),
            ], $cambios)
        );

        foreach ($partidas as $partida) {
            $pago->detalles()->create([
                'metodo_pago' => $partida[0],
                'monto' => $partida[1],
                'referencia' => null,
                'detalle_adicional' => null,
                'detalle_pago_original_id' => null,
            ]);
        }

        return $pago;
    }

    public function test_m33_compensacion_parcial(): void
    {
        $this->concederPermisoCaja(
            $this->admin,
            'caja.autorizar_compensacion'
        );

        $sesion = $this->abrir();

        $original = $this->movimientos->registrarGasto([
            'monto' => '200',
            'concepto' => 'Gasto original',
        ], $this->admin);

        $antes = $original->getAttributes();

        $compensacion = $this->compensarPrueba(
            $original,
            '50'
        );

        $this->assertEquals(
            $original->id,
            $compensacion->movimiento_original_id
        );

        $this->assertSame(
            'compensacion',
            $compensacion->tipo_movimiento
        );

        $this->assertSame('50.0000', $compensacion->monto);

        $this->assertEquals(
            $this->admin->id,
            $compensacion->autorizado_por
        );

        $this->assertSame(
            $antes,
            $original->fresh()->getAttributes()
        );

        $this->assertSame('350.0000', $this->saldo($sesion));
    }

    public function test_m34_compensacion_total_acumulada(): void
    {
        $this->concederPermisoCaja(
            $this->admin,
            'caja.autorizar_compensacion'
        );

        $sesion = $this->abrir();

        $original = $this->movimientos->registrarRetiro([
            'monto' => '200',
            'concepto' => 'Retiro original',
        ], $this->admin);

        $this->compensarPrueba($original, '50');
        $this->compensarPrueba($original, '150');

        $this->assertSame('500.0000', $this->saldo($sesion));

        $this->assertSame(
            2,
            $original->compensaciones()->count()
        );
    }

    public function test_m35_rechaza_exceso_compensacion(): void
    {
        $this->concederPermisoCaja(
            $this->admin,
            'caja.autorizar_compensacion'
        );

        $this->abrir();

        $original = $this->movimientos->registrarGasto([
            'monto' => '100',
            'concepto' => 'Gasto original',
        ], $this->admin);

        $this->compensarPrueba($original, '60');

        try {
            $this->compensarPrueba($original, '40.0001');
            $this->fail('Se permitio compensar en exceso.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('monto', $e->errors());
        }

        $this->assertSame(
            1,
            $original->compensaciones()->count()
        );
    }

    public function test_m36_rechaza_compensacion_encadenada(): void
    {
        $this->concederPermisoCaja(
            $this->admin,
            'caja.autorizar_compensacion'
        );

        $this->abrir();

        $original = $this->movimientos->registrarGasto([
            'monto' => '100',
            'concepto' => 'Gasto original',
        ], $this->admin);

        $compensacion = $this->compensarPrueba(
            $original,
            '50'
        );

        $this->expectException(
            \Illuminate\Validation\ValidationException::class
        );

        $this->compensarPrueba($compensacion, '10');
    }

    public function test_m37_compensacion_sin_permiso(): void
    {
        $this->abrir();

        $original = $this->movimientos->registrarGasto([
            'monto' => '100',
            'concepto' => 'Gasto original',
        ], $this->admin);

        $permiso = \Illuminate\Support\Facades\DB::table('permisos')
            ->where('clave', 'caja.autorizar_compensacion')
            ->value('id');

        if ($permiso !== null) {
            \Illuminate\Support\Facades\DB::table('rol_permiso')
                ->where('rol_id', $this->admin->role_id)
                ->where('permiso_id', $permiso)
                ->delete();
        }

        $this->expectException(
            \Illuminate\Auth\Access\AuthorizationException::class
        );

        $this->compensarPrueba($original, '10');
    }

    public function test_m38_ingreso_financiero(): void
    {
        $this->concederPermisoCaja(
            $this->admin,
            'pagos.registrar'
        );

        $sesion = $this->abrir();

        $pago = $this->crearPagoPrueba([
            ['efectivo', '250.50'],
        ]);

        $detalle = $pago->detalles()->firstOrFail();

        $movimiento = $this->movimientos
            ->registrarMovimientoPago($detalle, $this->admin);

        $this->assertNotNull($movimiento);

        $this->assertSame('ingreso', $movimiento->tipo_movimiento);

        $this->assertSame('250.5000', $movimiento->monto);

        $this->assertEquals(
            $detalle->id,
            $movimiento->detalle_pago_id
        );

        $this->assertSame('750.5000', $this->saldo($sesion));
    }

    public function test_m39_pago_mixto(): void
    {
        $this->concederPermisoCaja(
            $this->admin,
            'pagos.registrar'
        );

        $sesion = $this->abrir();

        $pago = $this->crearPagoPrueba([
            ['efectivo', '100'],
            ['transferencia', '200'],
        ]);

        foreach ($pago->detalles()->get() as $detalle) {
            $resultado = $this->movimientos
                ->registrarMovimientoPago($detalle, $this->admin);

            if ($detalle->metodo_pago === 'efectivo') {
                $this->assertNotNull($resultado);
            } else {
                $this->assertNull($resultado);
            }
        }

        $this->assertSame(1, $sesion->movimientos()->count());

        $this->assertSame('600.0000', $this->saldo($sesion));
    }

    public function test_m40_pago_idempotente(): void
    {
        $this->concederPermisoCaja(
            $this->admin,
            'pagos.registrar'
        );

        $sesion = $this->abrir();

        $pago = $this->crearPagoPrueba([
            ['efectivo', '100'],
        ]);

        $detalle = $pago->detalles()->firstOrFail();

        $primero = $this->movimientos
            ->registrarMovimientoPago($detalle, $this->admin);

        $segundo = $this->movimientos
            ->registrarMovimientoPago($detalle, $this->admin);

        $this->assertEquals($primero->id, $segundo->id);

        $this->assertSame(1, $sesion->movimientos()->count());
    }

    public function test_m41_pago_pendiente_rechazado(): void
    {
        $this->concederPermisoCaja(
            $this->admin,
            'pagos.registrar'
        );

        $this->abrir();

        $pago = $this->crearPagoPrueba(
            [['efectivo', '100']],
            [
                'estado' => 'pendiente',
                'fecha_pago' => null,
            ]
        );

        $this->expectException(
            \Illuminate\Validation\ValidationException::class
        );

        $this->movimientos->registrarMovimientoPago(
            $pago->detalles()->firstOrFail(),
            $this->admin
        );
    }

    public function test_m42_pago_total_inconsistente(): void
    {
        $this->concederPermisoCaja(
            $this->admin,
            'pagos.registrar'
        );

        $this->abrir();

        $pago = $this->crearPagoPrueba(
            [['efectivo', '100']],
            ['monto_total' => '200']
        );

        $this->expectException(
            \Illuminate\Validation\ValidationException::class
        );

        $this->movimientos->registrarMovimientoPago(
            $pago->detalles()->firstOrFail(),
            $this->admin
        );
    }

    public function test_m43_reembolso_bloqueado(): void
    {
        $this->concederPermisoCaja(
            $this->admin,
            'pagos.registrar'
        );

        $this->abrir();

        $original = $this->crearPagoPrueba([
            ['efectivo', '100'],
        ]);

        $reembolso = $this->crearPagoPrueba(
            [['efectivo', '50']],
            ['pago_original_id' => $original->id]
        );

        $this->expectException(
            \Illuminate\Validation\ValidationException::class
        );

        $this->movimientos->registrarMovimientoPago(
            $reembolso->detalles()->firstOrFail(),
            $this->admin
        );
    }

    public function test_m44_movimiento_no_actualizable(): void
    {
        $this->abrir();

        $movimiento = $this->movimientos->registrarGasto([
            'monto' => '100',
            'concepto' => 'Gasto original',
        ], $this->admin);

        $this->expectException(\LogicException::class);

        $movimiento->update(['monto' => '200']);
    }

    public function test_m45_movimiento_no_eliminable(): void
    {
        $this->abrir();

        $movimiento = $this->movimientos->registrarGasto([
            'monto' => '100',
            'concepto' => 'Gasto original',
        ], $this->admin);

        $this->expectException(\LogicException::class);

        $movimiento->delete();
    }

    public function test_m46_cierre_sin_diferencia(): void
    {
        $sesion = $this->abrir();

        $cerrada = $this->sesiones->cerrar([
            'efectivo_contado' => '500',
        ], $this->admin);

        $this->assertEquals($sesion->id, $cerrada->id);

        $this->assertSame('cerrada', $cerrada->estado);

        $this->assertSame('500.0000', $cerrada->saldo_esperado);

        $this->assertSame('500.0000', $cerrada->efectivo_contado);

        $this->assertSame('0.0000', $cerrada->diferencia);

        $this->assertEquals(
            $this->admin->id,
            $cerrada->cerrada_por
        );

        $this->assertNotNull($cerrada->fecha_cierre);

        $this->assertNull($cerrada->caja_abierta_id);

        $this->assertNull($cerrada->empleado_abierto_id);
    }

    public function test_m47_cierre_con_faltante(): void
    {
        $this->abrir();

        $this->movimientos->registrarGasto([
            'monto' => '100',
            'concepto' => 'Gasto',
        ], $this->admin);

        $cerrada = $this->sesiones->cerrar([
            'efectivo_contado' => '390',
        ], $this->admin);

        $this->assertSame('400.0000', $cerrada->saldo_esperado);

        $this->assertSame('-10.0000', $cerrada->diferencia);
    }

    public function test_m48_cierre_con_sobrante(): void
    {
        $this->abrir();

        $cerrada = $this->sesiones->cerrar([
            'efectivo_contado' => '510',
        ], $this->admin);

        $this->assertSame('10.0000', $cerrada->diferencia);
    }

    public function test_m49_cierre_con_efectivo_cero(): void
    {
        $this->abrir();

        $cerrada = $this->sesiones->cerrar([
            'efectivo_contado' => '0',
        ], $this->admin);

        $this->assertSame('0.0000', $cerrada->efectivo_contado);

        $this->assertSame('-500.0000', $cerrada->diferencia);
    }

    public function test_m50_rechaza_doble_cierre(): void
    {
        $this->abrir();

        $this->sesiones->cerrar([
            'efectivo_contado' => '500',
        ], $this->admin);

        $this->expectException(
            \Illuminate\Validation\ValidationException::class
        );

        $this->sesiones->cerrar([
            'efectivo_contado' => '500',
        ], $this->admin);
    }

    public function test_m51_cierre_sin_permiso(): void
    {
        $sesion = $this->abrir();

        $permiso = \Illuminate\Support\Facades\DB::table('permisos')
            ->where('clave', 'caja.cerrar')
            ->value('id');

        \Illuminate\Support\Facades\DB::table('rol_permiso')
            ->where('rol_id', $this->admin->role_id)
            ->where('permiso_id', $permiso)
            ->delete();

        try {
            $this->sesiones->cerrar([
                'efectivo_contado' => '500',
            ], $this->admin);

            $this->fail('Se permitio cerrar sin permiso.');
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->assertSame(
                'abierta',
                $sesion->fresh()->estado
            );
        }
    }

    public function test_m52_cierre_rechaza_datos_manipulados(): void
    {
        $sesion = $this->abrir();

        foreach ([
            'saldo_esperado',
            'diferencia',
            'cerrada_por',
            'fecha_cierre',
            'estado',
            'sesion_caja_id',
        ] as $campo) {
            try {
                $this->sesiones->cerrar([
                    'efectivo_contado' => '500',
                    $campo => '1',
                ], $this->admin);

                $this->fail('Campo manipulado aceptado.');
            } catch (\Illuminate\Validation\ValidationException $e) {
                $this->assertArrayHasKey($campo, $e->errors());
            }
        }

        $this->assertSame('abierta', $sesion->fresh()->estado);
    }

    public function test_m53_cierre_rechaza_importes_invalidos(): void
    {
        $sesion = $this->abrir();

        foreach ([
            '-1',
            '1.00001',
            '100000000',
            10.5,
            null,
            [],
        ] as $importe) {
            try {
                $this->sesiones->cerrar([
                    'efectivo_contado' => $importe,
                ], $this->admin);

                $this->fail('Importe invalido aceptado.');
            } catch (\Illuminate\Validation\ValidationException $e) {
                $this->assertArrayHasKey(
                    'efectivo_contado',
                    $e->errors()
                );
            }
        }

        $this->assertSame('abierta', $sesion->fresh()->estado);
    }

    public function test_m54_cierre_con_movimientos_mixtos(): void
    {
        $this->concederPermisoCaja(
            $this->admin,
            'caja.autorizar_compensacion'
        );

        $this->concederPermisoCaja(
            $this->admin,
            'pagos.registrar'
        );

        $this->abrir();

        $gasto = $this->movimientos->registrarGasto([
            'monto' => '100',
            'concepto' => 'Gasto',
        ], $this->admin);

        $this->movimientos->registrarRetiro([
            'monto' => '50',
            'concepto' => 'Retiro',
        ], $this->admin);

        $this->compensarPrueba($gasto, '25');

        $pago = $this->crearPagoPrueba([
            ['efectivo', '300'],
            ['transferencia', '200'],
        ]);

        foreach ($pago->detalles()->get() as $detalle) {
            $this->movimientos->registrarMovimientoPago(
                $detalle,
                $this->admin
            );
        }

        $cerrada = $this->sesiones->cerrar([
            'efectivo_contado' => '675',
        ], $this->admin);

        $this->assertSame('675.0000', $cerrada->saldo_esperado);

        $this->assertSame('0.0000', $cerrada->diferencia);

        $this->assertSame(4, $cerrada->movimientos()->count());
    }

    public function test_m55_cierre_libera_caja_y_empleado(): void
    {
        $primera = $this->abrir();

        $this->sesiones->cerrar([
            'efectivo_contado' => '500',
        ], $this->admin);

        $caja = \App\Models\Caja::query()
            ->findOrFail($primera->caja_id);

        $segunda = $this->sesiones->abrir([
            'caja_id' => $caja->id,
            'fondo_inicial' => '100',
        ], $this->admin);

        $this->assertNotEquals($primera->id, $segunda->id);

        $this->assertSame('abierta', $segunda->estado);

        $this->assertSame('cerrada', $primera->fresh()->estado);
    }

    public function test_m56_cierre_rollback(): void
    {
        $sesion = $this->abrir();

        try {
            \Illuminate\Support\Facades\DB::transaction(function () {
                $this->sesiones->cerrar([
                    'efectivo_contado' => '500',
                ], $this->admin);

                throw new \RuntimeException('rollback de prueba');
            });

            $this->fail('No se ejecuto el rollback.');
        } catch (\RuntimeException $e) {
            $this->assertSame('rollback de prueba', $e->getMessage());
        }

        $actual = $sesion->fresh();

        $this->assertSame('abierta', $actual->estado);

        $this->assertNull($actual->fecha_cierre);

        $this->assertNull($actual->saldo_esperado);

        $this->assertNull($actual->cerrada_por);
    }

    public function test_m57_no_registra_gasto_despues_del_cierre(): void
    {
        $this->abrir();

        $this->sesiones->cerrar([
            'efectivo_contado' => '500',
        ], $this->admin);

        $this->expectException(
            \Illuminate\Validation\ValidationException::class
        );

        $this->movimientos->registrarGasto([
            'monto' => '10',
            'concepto' => 'Gasto posterior',
        ], $this->admin);
    }


    public function test_m58_cierre_identificado_idempotente(): void
    {
        $sesion = $this->abrir();

        $primera = $this->sesiones->cerrarIdentificada(
            $sesion->id,
            ['efectivo_contado' => '500'],
            $this->admin
        );

        $segunda = $this->sesiones->cerrarIdentificada(
            $sesion->id,
            ['efectivo_contado' => '500.0000'],
            $this->admin
        );

        $this->assertSame(
            $primera->getAttributes(),
            $segunda->getAttributes()
        );
    }

    public function test_m59_cierre_antiguo_no_cierra_nueva_sesion(): void
    {
        $primera = $this->abrir();

        $this->sesiones->cerrarIdentificada(
            $primera->id,
            ['efectivo_contado' => '500'],
            $this->admin
        );

        $segunda = $this->abrir();

        $this->sesiones->cerrarIdentificada(
            $primera->id,
            ['efectivo_contado' => '500'],
            $this->admin
        );

        $this->assertSame(
            'abierta',
            $segunda->fresh()->estado
        );
    }

    public function test_m60_cierre_identificado_rechaza_otro_importe(): void
    {
        $sesion = $this->abrir();

        $this->sesiones->cerrarIdentificada(
            $sesion->id,
            ['efectivo_contado' => '500'],
            $this->admin
        );

        $this->expectException(
            \Illuminate\Validation\ValidationException::class
        );

        $this->sesiones->cerrarIdentificada(
            $sesion->id,
            ['efectivo_contado' => '501'],
            $this->admin
        );
    }

    public function test_m61_gasto_antiguo_rechazado(): void
    {
        $primera = $this->abrir();

        $this->sesiones->cerrarIdentificada(
            $primera->id,
            ['efectivo_contado' => '500'],
            $this->admin
        );

        $segunda = $this->abrir();

        try {
            $this->movimientos->registrarGasto([
                'monto' => '10',
                'concepto' => 'Solicitud antigua',
            ], $this->admin, $primera->id);

            $this->fail('Solicitud antigua aceptada.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey(
                'sesion_caja',
                $e->errors()
            );
        }

        $this->assertSame(
            0,
            $segunda->movimientos()->count()
        );
    }

    public function test_m62_gasto_http_identificado(): void
    {
        $sesion = $this->abrir();

        $this->actingAs($this->admin)
            ->post(route('caja.movimientos.gasto'), [
                'sesion_id' => $sesion->id,
                'monto' => '50',
                'concepto' => 'Gasto HTTP',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('caja.sesion.actual'));

        $this->assertSame(
            '450.0000',
            $this->saldo($sesion)
        );
    }

    public function test_m63_retiro_http_identificado(): void
    {
        $sesion = $this->abrir();

        $this->actingAs($this->admin)
            ->post(route('caja.movimientos.retiro'), [
                'sesion_id' => $sesion->id,
                'monto' => '50',
                'concepto' => 'Retiro HTTP',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('caja.sesion.actual'));

        $this->assertSame(
            '450.0000',
            $this->saldo($sesion)
        );
    }

    public function test_m64_http_rechaza_campos_manipulados(): void
    {
        $sesion = $this->abrir();

        $this->actingAs($this->admin)
            ->post(route('caja.movimientos.gasto'), [
                'sesion_id' => $sesion->id,
                'monto' => '50',
                'concepto' => 'Gasto HTTP',
                'autorizado_por' => 999,
            ])
            ->assertSessionHasErrors('autorizado_por');

        $this->assertSame(
            0,
            $sesion->movimientos()->count()
        );
    }

    public function test_m65_cierre_http(): void
    {
        $sesion = $this->abrir();

        $this->actingAs($this->admin)
            ->post(route('caja.sesion.cerrar', $sesion->id), [
                'efectivo_contado' => '490',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(
                route('caja.sesion.detalle', $sesion->id)
            );

        $this->assertSame(
            '-10.0000',
            $sesion->fresh()->diferencia
        );
    }

    public function test_m66_renderizado_operaciones(): void
    {
        $this->abrir();

        $this->actingAs($this->admin)
            ->get(route('caja.sesion.actual'))
            ->assertOk()
            ->assertSee('Registrar gasto')
            ->assertSee('Registrar retiro')
            ->assertSee('Movimientos de caja')
            ->assertSee('Cerrar caja');
    }

    public function test_m67_historial_y_detalle_http(): void
    {
        $sesion = $this->abrir();

        $this->sesiones->cerrarIdentificada(
            $sesion->id,
            ['efectivo_contado' => '490'],
            $this->admin
        );

        $this->actingAs($this->admin)
            ->get(route('caja.sesion.historial'))
            ->assertOk()
            ->assertSee('Historial de sesiones');

        $this->get(
            route('caja.sesion.detalle', $sesion->id)
        )
            ->assertOk()
            ->assertSee('Faltante')
            ->assertSee('-10.0000');
    }

    public function test_m68_compensacion_http(): void
    {
        $this->concederPermisoCaja(
            $this->admin,
            'caja.autorizar_compensacion'
        );

        $sesion = $this->abrir();

        $original = $this->movimientos->registrarGasto([
            'monto' => '100',
            'concepto' => 'Gasto original',
        ], $this->admin);

        $this->actingAs($this->admin)
            ->post(
                route('caja.movimientos.compensacion', $original->id),
                [
                    'monto' => '50',
                    'concepto' => 'Compensacion',
                    'observaciones' => 'Efectivo reintegrado',
                ]
            )
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('caja.sesion.actual'));

        $this->assertSame(
            '450.0000',
            $this->saldo($sesion)
        );
    }

    public function test_m69_invitados_no_operan_caja(): void
    {
        $this->get(route('caja.sesion.historial'))
            ->assertRedirect(route('login'));

        $this->post(route('caja.movimientos.gasto'))
            ->assertRedirect(route('login'));

        $this->post(route('caja.movimientos.retiro'))
            ->assertRedirect(route('login'));

        $this->post(route('caja.sesion.cerrar', 1))
            ->assertRedirect(route('login'));
    }



    public function test_m70_retiro_antiguo_rechazado(): void
    {
        $primera = $this->abrir();

        $this->sesiones->cerrarIdentificada(
            $primera->id,
            ['efectivo_contado' => '500'],
            $this->admin
        );

        $segunda = $this->abrir();

        try {
            $this->movimientos->registrarRetiro([
                'monto' => '10',
                'concepto' => 'Retiro antiguo',
            ], $this->admin, $primera->id);

            $this->fail('Retiro antiguo aceptado.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey(
                'sesion_caja',
                $e->errors()
            );
        }

        $this->assertSame(
            0,
            $segunda->movimientos()->count()
        );
    }

    public function test_m71_http_requiere_identificador_sesion(): void
    {
        $sesion = $this->abrir();

        foreach (['gasto', 'retiro'] as $tipo) {
            $this->actingAs($this->admin)
                ->post(route('caja.movimientos.'.$tipo), [
                    'monto' => '10',
                    'concepto' => 'Sin identificador',
                ])
                ->assertSessionHasErrors('sesion_id');
        }

        $this->assertSame(
            0,
            $sesion->movimientos()->count()
        );
    }

    public function test_m72_http_gasto_sin_permiso(): void
    {
        $sesion = $this->abrir();

        $permiso = \Illuminate\Support\Facades\DB::table('permisos')
            ->where('clave', 'caja.registrar_gasto')
            ->value('id');

        \Illuminate\Support\Facades\DB::table('rol_permiso')
            ->where('rol_id', $this->admin->role_id)
            ->where('permiso_id', $permiso)
            ->delete();

        $this->actingAs($this->admin)
            ->post(route('caja.movimientos.gasto'), [
                'sesion_id' => $sesion->id,
                'monto' => '10',
                'concepto' => 'Gasto no autorizado',
            ])
            ->assertForbidden();

        $this->assertSame(
            0,
            $sesion->movimientos()->count()
        );
    }

    public function test_m73_http_retiro_sin_permiso(): void
    {
        $sesion = $this->abrir();

        $permiso = \Illuminate\Support\Facades\DB::table('permisos')
            ->where('clave', 'caja.registrar_retiro')
            ->value('id');

        \Illuminate\Support\Facades\DB::table('rol_permiso')
            ->where('rol_id', $this->admin->role_id)
            ->where('permiso_id', $permiso)
            ->delete();

        $this->actingAs($this->admin)
            ->post(route('caja.movimientos.retiro'), [
                'sesion_id' => $sesion->id,
                'monto' => '10',
                'concepto' => 'Retiro no autorizado',
            ])
            ->assertForbidden();

        $this->assertSame(
            0,
            $sesion->movimientos()->count()
        );
    }

    public function test_m74_http_cierre_sin_permiso(): void
    {
        $sesion = $this->abrir();

        $permiso = \Illuminate\Support\Facades\DB::table('permisos')
            ->where('clave', 'caja.cerrar')
            ->value('id');

        \Illuminate\Support\Facades\DB::table('rol_permiso')
            ->where('rol_id', $this->admin->role_id)
            ->where('permiso_id', $permiso)
            ->delete();

        $this->actingAs($this->admin)
            ->post(route('caja.sesion.cerrar', $sesion->id), [
                'efectivo_contado' => '500',
            ])
            ->assertForbidden();

        $this->assertSame(
            'abierta',
            $sesion->fresh()->estado
        );
    }

    public function test_m75_http_compensacion_sin_permiso(): void
    {
        $sesion = $this->abrir();

        $original = $this->movimientos->registrarGasto([
            'monto' => '100',
            'concepto' => 'Gasto original',
        ], $this->admin);

        $this->actingAs($this->admin)
            ->post(
                route('caja.movimientos.compensacion', $original->id),
                [
                    'monto' => '50',
                    'concepto' => 'Compensacion',
                    'observaciones' => 'No autorizada',
                ]
            )
            ->assertForbidden();

        $this->assertSame(
            1,
            $sesion->movimientos()->count()
        );
    }

    public function test_m76_http_cierre_repetido_no_modifica_resultado(): void
    {
        $sesion = $this->abrir();

        $ruta = route('caja.sesion.cerrar', $sesion->id);

        $this->actingAs($this->admin)
            ->post($ruta, [
                'efectivo_contado' => '490',
            ])
            ->assertSessionHasNoErrors();

        $original = $sesion->fresh()->getAttributes();

        $this->post($ruta, [
            'efectivo_contado' => '490.0000',
        ])
            ->assertSessionHasNoErrors();

        $this->assertSame(
            $original,
            $sesion->fresh()->getAttributes()
        );

        $this->post($ruta, [
            'efectivo_contado' => '500',
        ])
            ->assertSessionHasErrors('efectivo_contado');

        $this->assertSame(
            $original,
            $sesion->fresh()->getAttributes()
        );
    }

    public function test_m77_http_compensacion_requiere_justificacion(): void
    {
        $this->concederPermisoCaja(
            $this->admin,
            'caja.autorizar_compensacion'
        );

        $sesion = $this->abrir();

        $original = $this->movimientos->registrarGasto([
            'monto' => '100',
            'concepto' => 'Gasto original',
        ], $this->admin);

        $this->actingAs($this->admin)
            ->post(
                route('caja.movimientos.compensacion', $original->id),
                [
                    'monto' => '50',
                    'concepto' => 'Compensacion',
                ]
            )
            ->assertSessionHasErrors('observaciones');

        $this->assertSame(
            1,
            $sesion->movimientos()->count()
        );
    }

    public function test_m78_contencion_caja_entre_conexiones(): void
    {
        $principal = \Illuminate\Support\Facades\DB::connection();

        $nombre = 'joysys_caja_concurrencia';

        config([
            'database.connections.'.$nombre =>
                config('database.connections.mysql'),
        ]);

        $secundaria = \Illuminate\Support\Facades\DB::connection(
            $nombre
        );

        $bloqueo = 'joysys:caja:'.sha1(
            $principal->getDatabaseName()
        );

        try {
            $idPrincipal = $principal->selectOne(
                'SELECT CONNECTION_ID() AS id',
                [],
                false
            );

            $idSecundaria = $secundaria->selectOne(
                'SELECT CONNECTION_ID() AS id',
                [],
                false
            );

            $this->assertNotSame(
                (string) $idPrincipal->id,
                (string) $idSecundaria->id
            );

            $adquirido = $principal->selectOne(
                'SELECT GET_LOCK(?, 0) AS adquirido',
                [$bloqueo],
                false
            );

            $this->assertSame(
                1,
                (int) $adquirido->adquirido
            );

            $contencion = $secundaria->selectOne(
                'SELECT GET_LOCK(?, 0) AS adquirido',
                [$bloqueo],
                false
            );

            $this->assertSame(
                0,
                (int) $contencion->adquirido
            );

            $liberado = $principal->selectOne(
                'SELECT RELEASE_LOCK(?) AS liberado',
                [$bloqueo],
                false
            );

            $this->assertSame(
                1,
                (int) $liberado->liberado
            );

            $nuevo = $secundaria->selectOne(
                'SELECT GET_LOCK(?, 0) AS adquirido',
                [$bloqueo],
                false
            );

            $this->assertSame(
                1,
                (int) $nuevo->adquirido
            );
        } finally {
            $principal->selectOne(
                'SELECT RELEASE_LOCK(?) AS liberado',
                [$bloqueo],
                false
            );

            $secundaria->selectOne(
                'SELECT RELEASE_LOCK(?) AS liberado',
                [$bloqueo],
                false
            );

            \Illuminate\Support\Facades\DB::purge($nombre);
        }
    }

    public function test_m79_http_cierre_rechaza_campos_derivados(): void
    {
        $sesion = $this->abrir();

        $this->actingAs($this->admin)
            ->post(route('caja.sesion.cerrar', $sesion->id), [
                'efectivo_contado' => '500',
                'saldo_esperado' => '500',
                'diferencia' => '0',
            ])
            ->assertSessionHasErrors();

        $this->assertSame(
            'abierta',
            $sesion->fresh()->estado
        );
    }

    public function test_m80_http_retiro_antiguo_no_afecta_nueva_sesion(): void
    {
        $primera = $this->abrir();

        $this->sesiones->cerrarIdentificada(
            $primera->id,
            ['efectivo_contado' => '500'],
            $this->admin
        );

        $segunda = $this->abrir();

        $this->actingAs($this->admin)
            ->post(route('caja.movimientos.retiro'), [
                'sesion_id' => $primera->id,
                'monto' => '50',
                'concepto' => 'Solicitud antigua',
            ])
            ->assertSessionHasErrors('sesion_caja');

        $this->assertSame(
            0,
            $segunda->movimientos()->count()
        );

        $this->assertSame(
            '500.0000',
            $this->saldo($segunda)
        );
    }

    public function test_m81_http_cierre_sesion_inexistente(): void
    {
        $sesion = $this->abrir();

        $idInexistente = (int) \App\Models\SesionCaja::query()
            ->max('id') + 1;

        $this->actingAs($this->admin)
            ->post(
                route('caja.sesion.cerrar', $idInexistente),
                ['efectivo_contado' => '500']
            )
            ->assertNotFound();

        $this->assertSame(
            'abierta',
            $sesion->fresh()->estado
        );
    }



    public function test_m82_mysql_impide_actualizar_movimiento(): void
    {
        $sesion = $this->abrir();

        $movimiento = $this->movimientos->registrarGasto([
            'monto' => '100',
            'concepto' => 'Movimiento original',
        ], $this->admin);

        $original = $movimiento->fresh()->getAttributes();

        try {
            \Illuminate\Support\Facades\DB::table('movimientos_caja')
                ->where('id', $movimiento->id)
                ->update([
                    'monto' => '1.0000',
                ]);

            $this->fail(
                'MySQL permitio modificar el movimiento.'
            );
        } catch (\Illuminate\Database\QueryException $e) {
            $this->assertSame(
                '45000',
                $e->errorInfo[0] ?? null
            );

            $this->assertStringContainsString(
                'Los movimientos de caja son inmutables',
                $e->getMessage()
            );
        }

        $this->assertSame(
            $original,
            $movimiento->fresh()->getAttributes()
        );

        $this->assertSame(
            '400.0000',
            $this->saldo($sesion)
        );
    }

    public function test_m83_mysql_impide_eliminar_movimiento(): void
    {
        $sesion = $this->abrir();

        $movimiento = $this->movimientos->registrarRetiro([
            'monto' => '100',
            'concepto' => 'Retiro original',
        ], $this->admin);

        $original = $movimiento->fresh()->getAttributes();

        try {
            \Illuminate\Support\Facades\DB::table('movimientos_caja')
                ->where('id', $movimiento->id)
                ->delete();

            $this->fail(
                'MySQL permitio eliminar el movimiento.'
            );
        } catch (\Illuminate\Database\QueryException $e) {
            $this->assertSame(
                '45000',
                $e->errorInfo[0] ?? null
            );

            $this->assertStringContainsString(
                'Los movimientos de caja no pueden eliminarse',
                $e->getMessage()
            );
        }

        $this->assertDatabaseHas('movimientos_caja', [
            'id' => $movimiento->id,
        ]);

        $this->assertSame(
            $original,
            $movimiento->fresh()->getAttributes()
        );

        $this->assertSame(
            '400.0000',
            $this->saldo($sesion)
        );
    }

}
