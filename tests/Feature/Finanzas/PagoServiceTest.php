<?php

namespace Tests\Feature\Finanzas;

use App\Models\Caja;
use App\Models\DetallePago;
use App\Models\MovimientoCaja;
use App\Models\Pago;
use App\Models\User;
use App\Models\Venta;
use App\Services\Caja\SesionCajaService;
use App\Services\Finanzas\PagoService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class PagoServiceTest extends TestCase
{
    private PagoService $servicio;

    private User $usuario;

    private int $sucursal;

    private Venta $venta;

    private int $secuencia = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->servicio = app(PagoService::class);

        $this->sucursal = $this->crearSucursal();

        $this->usuario = $this->crearUsuario(
            $this->sucursal
        );

        $this->venta = $this->crearVenta(
            $this->usuario,
            $this->sucursal
        );
    }

    private function crearSucursal(): int
    {
        $codigo = 'FIN-SUC-'.(++$this->secuencia);

        return DB::table('sucursales')->insertGetId([
            'codigo' => $codigo,
            'nombre' => $codigo,
            'estado' => 'activa',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function crearUsuario(
        int $sucursal,
        array $permisos = [
            'pagos.ver',
            'pagos.registrar',
            'caja.ver',
            'caja.abrir',
            'caja.cerrar',
        ]
    ): User {
        $slug = 'finanzas-prueba-'.(++$this->secuencia);

        $rol = DB::table('roles')->insertGetId([
            'nombre' => $slug,
            'slug' => $slug,
            'estado' => 'activo',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($permisos as $clave) {
            $permiso = DB::table('permisos')
                ->where('clave', $clave)
                ->value('id');

            if ($permiso === null) {
                $permiso = DB::table('permisos')
                    ->insertGetId([
                        'clave' => $clave,
                        'nombre' => $clave,
                        'modulo' => explode('.', $clave)[0],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
            }

            DB::table('rol_permiso')->insert([
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

        DB::table('empleado_sucursal')->insert([
            'usuario_id' => $usuario->getKey(),
            'sucursal_id' => $sucursal,
            'es_principal' => true,
            'fecha_inicio' => now()->subDay(),
            'fecha_fin' => null,
            'estado' => 'activo',
            'asignado_por' => $usuario->getKey(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $usuario;
    }

    private function crearVenta(
        User $usuario,
        int $sucursal
    ): Venta {
        return Venta::query()->create([
            'sucursal_id' => $sucursal,
            'cliente_id' => null,
            'usuario_id' => $usuario->getKey(),
            'folio' => 'FIN-'.(++$this->secuencia),
            'subtotal' => '1000.0000',
            'descuento_total' => '0.0000',
            'impuestos' => '0.0000',
            'total' => '1000.0000',
            'estado' => 'completada',
        ]);
    }

    private function abrirCaja(): void
    {
        $caja = Caja::query()->create([
            'sucursal_id' => $this->sucursal,
            'codigo' => 'FIN-CAJA-01',
            'nombre' => 'Caja financiera',
            'moneda' => 'MXN',
            'estado' => 'activa',
        ]);

        app(SesionCajaService::class)->abrir([
            'caja_id' => $caja->getKey(),
            'fondo_inicial' => '500.0000',
        ], $this->usuario);
    }

    private function datos(
        string $monto = '100.0000',
        string $metodo = 'efectivo'
    ): array {
        return [
            'referencia_tipo' => 'venta',
            'referencia_id' => $this->venta->getKey(),
            'monto_total' => $monto,
            'moneda' => 'MXN',
            'detalles' => [
                [
                    'metodo_pago' => $metodo,
                    'monto' => $monto,
                ],
            ],
        ];
    }

    private function pendiente(
        string $monto = '100.0000',
        string $metodo = 'efectivo'
    ): Pago {
        return $this->servicio->registrarPendiente(
            $this->datos($monto, $metodo),
            $this->usuario
        );
    }

    private function errorValidacion(
        callable $operacion,
        string $campo
    ): void {
        try {
            $operacion();

            $this->fail(
                'Se esperaba una excepcion de validacion.'
            );
        } catch (ValidationException $e) {
            $this->assertArrayHasKey(
                $campo,
                $e->errors()
            );
        }
    }

    public function test_f01_registro_pendiente(): void
    {
        $pago = $this->pendiente();

        $this->assertSame('pendiente', $pago->estado);
        $this->assertSame('100.0000', $pago->monto_total);
        $this->assertNull($pago->fecha_pago);
        $this->assertNull($pago->pago_original_id);
        $this->assertCount(1, $pago->detalles);
        $this->assertSame(0, MovimientoCaja::count());
    }

    public function test_f02_pago_mixto(): void
    {
        $datos = $this->datos('300');

        $datos['detalles'] = [
            [
                'metodo_pago' => 'efectivo',
                'monto' => '100',
            ],
            [
                'metodo_pago' => 'transferencia',
                'monto' => '200',
            ],
        ];

        $pago = $this->servicio->registrarPendiente(
            $datos,
            $this->usuario
        );

        $this->assertCount(2, $pago->detalles);
        $this->assertSame('300.0000', $pago->monto_total);
    }

    public function test_f03_rechaza_total_incorrecto(): void
    {
        $datos = $this->datos();

        $datos['detalles'][0]['monto'] = '99';

        $this->errorValidacion(
            fn () => $this->servicio->registrarPendiente(
                $datos,
                $this->usuario
            ),
            'monto_total'
        );

        $this->assertSame(0, Pago::count());
    }

    public function test_f04_rechaza_saldo_favor(): void
    {
        $this->errorValidacion(
            fn () => $this->pendiente('100', 'saldo_favor'),
            'detalles'
        );

        $this->assertSame(0, Pago::count());
    }

    public function test_f05_protege_campos(): void
    {
        $datos = $this->datos();

        $datos['estado'] = 'confirmado';

        $this->errorValidacion(
            fn () => $this->servicio->registrarPendiente(
                $datos,
                $this->usuario
            ),
            'pago'
        );
    }

    public function test_f06_referencia_no_admitida(): void
    {
        $datos = $this->datos();

        $datos['referencia_tipo'] = 'apartado';

        $this->errorValidacion(
            fn () => $this->servicio->registrarPendiente(
                $datos,
                $this->usuario
            ),
            'referencia_tipo'
        );
    }

    public function test_f07_venta_inexistente(): void
    {
        $datos = $this->datos();

        $datos['referencia_id'] = PHP_INT_MAX;

        $this->expectException(
            ModelNotFoundException::class
        );

        $this->servicio->registrarPendiente(
            $datos,
            $this->usuario
        );
    }

    public function test_f08_rechaza_exceso_venta(): void
    {
        $this->errorValidacion(
            fn () => $this->pendiente('1000.0001'),
            'monto_total'
        );

        $this->assertSame(0, Pago::count());
    }

    public function test_f09_pendientes_reservan_importe(): void
    {
        $this->pendiente('700');

        $this->errorValidacion(
            fn () => $this->pendiente('300.0001'),
            'monto_total'
        );

        $this->assertSame(1, Pago::count());
    }

    public function test_f10_cancelacion_libera_importe(): void
    {
        $pago = $this->pendiente('1000');

        $cancelado = $this->servicio->cancelar(
            $pago->getKey(),
            $this->usuario
        );

        $this->assertSame('cancelado', $cancelado->estado);
        $this->assertNull($cancelado->fecha_pago);

        $nuevo = $this->pendiente('1000');

        $this->assertSame('pendiente', $nuevo->estado);
        $this->assertSame(2, Pago::count());
    }

    public function test_f11_cancelacion_idempotente(): void
    {
        $pago = $this->pendiente();

        $primero = $this->servicio->cancelar(
            $pago->getKey(),
            $this->usuario
        );

        $segundo = $this->servicio->cancelar(
            $pago->getKey(),
            $this->usuario
        );

        $this->assertSame(
            $primero->getKey(),
            $segundo->getKey()
        );

        $this->assertSame('cancelado', $segundo->estado);
        $this->assertSame(0, MovimientoCaja::count());
    }

    public function test_f12_no_confirma_cancelado(): void
    {
        $pago = $this->pendiente();

        $this->servicio->cancelar(
            $pago->getKey(),
            $this->usuario
        );

        $this->errorValidacion(
            fn () => $this->servicio->confirmar(
                $pago->getKey(),
                $this->usuario
            ),
            'pago'
        );
    }

    public function test_f13_confirmacion_efectivo(): void
    {
        $this->abrirCaja();

        $pago = $this->pendiente();

        $confirmado = $this->servicio->confirmar(
            $pago->getKey(),
            $this->usuario
        );

        $this->assertSame(
            'confirmado',
            $confirmado->estado
        );

        $this->assertNotNull($confirmado->fecha_pago);

        $movimiento = $confirmado->detalles
            ->first()
            ->movimientoCaja;

        $this->assertNotNull($movimiento);
        $this->assertSame('ingreso', $movimiento->tipo_movimiento);
        $this->assertSame('100.0000', $movimiento->monto);
        $this->assertSame(1, MovimientoCaja::count());
    }

    public function test_f14_transferencia_sin_movimiento(): void
    {
        $pago = $this->servicio->registrarConfirmado(
            $this->datos('100', 'transferencia'),
            $this->usuario
        );

        $this->assertSame('confirmado', $pago->estado);
        $this->assertSame(0, MovimientoCaja::count());
    }

    public function test_f15_confirmacion_mixta(): void
    {
        $this->abrirCaja();

        $datos = $this->datos('300');

        $datos['detalles'] = [
            [
                'metodo_pago' => 'efectivo',
                'monto' => '75.1234',
            ],
            [
                'metodo_pago' => 'tarjeta_debito',
                'monto' => '224.8766',
            ],
        ];

        $pago = $this->servicio->registrarConfirmado(
            $datos,
            $this->usuario
        );

        $this->assertSame('confirmado', $pago->estado);
        $this->assertCount(2, $pago->detalles);
        $this->assertSame(1, MovimientoCaja::count());

        $this->assertSame(
            '75.1234',
            MovimientoCaja::firstOrFail()->monto
        );
    }

    public function test_f16_confirmacion_idempotente(): void
    {
        $this->abrirCaja();

        $pago = $this->servicio->registrarConfirmado(
            $this->datos(),
            $this->usuario
        );

        $fecha = $pago->fecha_pago->toDateTimeString();

        $repetido = $this->servicio->confirmar(
            $pago->getKey(),
            $this->usuario
        );

        $this->assertSame(
            $pago->getKey(),
            $repetido->getKey()
        );

        $this->assertSame(
            $fecha,
            $repetido->fecha_pago->toDateTimeString()
        );

        $this->assertSame(1, Pago::count());
        $this->assertSame(1, MovimientoCaja::count());
    }

    public function test_f17_no_cancela_confirmado(): void
    {
        $pago = $this->servicio->registrarConfirmado(
            $this->datos('100', 'transferencia'),
            $this->usuario
        );

        $this->errorValidacion(
            fn () => $this->servicio->cancelar(
                $pago->getKey(),
                $this->usuario
            ),
            'pago'
        );

        $this->assertSame(
            'confirmado',
            $pago->refresh()->estado
        );
    }

    public function test_f18_rollback_registro_confirmado(): void
    {
        try {
            $this->servicio->registrarConfirmado(
                $this->datos(),
                $this->usuario
            );

            $this->fail('Se esperaba rechazo sin caja abierta.');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }

        $this->assertSame(0, Pago::count());
        $this->assertSame(0, DetallePago::count());
        $this->assertSame(0, MovimientoCaja::count());
    }

    public function test_f19_rollback_confirmacion(): void
    {
        $pago = $this->pendiente();

        try {
            $this->servicio->confirmar(
                $pago->getKey(),
                $this->usuario
            );

            $this->fail('Se esperaba rechazo sin caja abierta.');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }

        $pago->refresh();

        $this->assertSame('pendiente', $pago->estado);
        $this->assertNull($pago->fecha_pago);
        $this->assertSame(1, DetallePago::count());
        $this->assertSame(0, MovimientoCaja::count());
    }

    public function test_f20_rechaza_usuario_sin_permiso(): void
    {
        $usuario = $this->crearUsuario(
            $this->sucursal,
            ['pagos.ver']
        );

        $this->expectException(
            AuthorizationException::class
        );

        $this->servicio->registrarPendiente(
            $this->datos(),
            $usuario
        );
    }

    public function test_f21_aislamiento_usuario(): void
    {
        $pago = $this->pendiente();

        $otro = $this->crearUsuario($this->sucursal);

        $this->expectException(
            ModelNotFoundException::class
        );

        $this->servicio->confirmar(
            $pago->getKey(),
            $otro
        );
    }

    public function test_f22_aislamiento_sucursal(): void
    {
        $pago = $this->pendiente();

        $otra = $this->crearSucursal();

        $usuario = $this->crearUsuario($otra);

        $this->expectException(
            ModelNotFoundException::class
        );

        $this->servicio->consultar(
            $pago->getKey(),
            $usuario
        );
    }

    public function test_f23_consulta_propia(): void
    {
        $pago = $this->pendiente();

        $consulta = $this->servicio->consultar(
            $pago->getKey(),
            $this->usuario
        );

        $this->assertSame(
            $pago->getKey(),
            $consulta->getKey()
        );

        $this->assertCount(1, $consulta->detalles);
    }

    public function test_f24_venta_de_otro_usuario(): void
    {
        $otro = $this->crearUsuario($this->sucursal);

        $this->expectException(
            AuthorizationException::class
        );

        $this->servicio->registrarPendiente(
            $this->datos(),
            $otro
        );
    }

    public function test_f25_rechaza_confirmacion_inconsistente(): void
    {
        $pago = $this->pendiente();

        DB::table('detalle_pagos')
            ->where('pago_id', $pago->getKey())
            ->update([
                'monto' => '99.0000',
            ]);

        $this->errorValidacion(
            fn () => $this->servicio->confirmar(
                $pago->getKey(),
                $this->usuario
            ),
            'monto_total'
        );

        $this->assertSame(
            'pendiente',
            $pago->refresh()->estado
        );
    }

    public function test_f26_confirmados_ocupan_importe(): void
    {
        $this->servicio->registrarConfirmado(
            $this->datos('700', 'transferencia'),
            $this->usuario
        );

        $this->errorValidacion(
            fn () => $this->pendiente('300.0001'),
            'monto_total'
        );
    }

    public function test_f27_libera_bloqueo(): void
    {
        $this->pendiente();

        $conexion = DB::connection();

        $nombre = 'joysys:caja:'.sha1(
            $conexion->getDatabaseName()
        );

        $resultado = $conexion->selectOne(
            'SELECT IS_FREE_LOCK(?) AS libre',
            [$nombre],
            false
        );

        $this->assertSame(1, (int) $resultado->libre);
    }

    public function test_f28_libera_bloqueo_tras_fallo(): void
    {
        $this->errorValidacion(
            fn () => $this->pendiente('1001'),
            'monto_total'
        );

        $conexion = DB::connection();

        $nombre = 'joysys:caja:'.sha1(
            $conexion->getDatabaseName()
        );

        $resultado = $conexion->selectOne(
            'SELECT IS_FREE_LOCK(?) AS libre',
            [$nombre],
            false
        );

        $this->assertSame(1, (int) $resultado->libre);
    }


    public function test_f29_registro_pendiente_idempotente(): void
    {
        $datos = $this->datos('100.0000', 'transferencia');

        $primero = $this->servicio->registrarPendiente(
            $datos, $this->usuario, 'pendiente-01'
        );

        $segundo = $this->servicio->registrarPendiente(
            $datos, $this->usuario, 'pendiente-01'
        );

        $this->assertSame($primero->id, $segundo->id);
        $this->assertSame('pendiente', $segundo->estado);
        $this->assertSame(1, \App\Models\Pago::count());
        $this->assertSame(1, \App\Models\DetallePago::count());
        $this->assertSame(1, \App\Models\OperacionIdempotente::count());
    }

    public function test_f30_registro_confirmado_idempotente(): void
    {
        $this->abrirCaja();

        $datos = $this->datos('100.0000', 'efectivo');

        $primero = $this->servicio->registrarConfirmado(
            $datos, $this->usuario, 'confirmado-01'
        );

        $segundo = $this->servicio->registrarConfirmado(
            $datos, $this->usuario, 'confirmado-01'
        );

        $this->assertSame($primero->id, $segundo->id);
        $this->assertSame('confirmado', $segundo->estado);
        $this->assertSame(1, \App\Models\Pago::count());
        $this->assertSame(1, \App\Models\DetallePago::count());
        $this->assertSame(1, \App\Models\MovimientoCaja::count());
        $this->assertSame(1, \App\Models\OperacionIdempotente::count());
    }

    public function test_f31_rechaza_clave_con_datos_distintos(): void
    {
        $this->servicio->registrarPendiente(
            $this->datos('100.0000', 'transferencia'),
            $this->usuario,
            'conflicto-01'
        );

        try {
            $this->servicio->registrarPendiente(
                $this->datos('200.0000', 'transferencia'),
                $this->usuario,
                'conflicto-01'
            );

            $this->fail('Se permitio reutilizar una clave.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }

        $this->assertSame(1, \App\Models\Pago::count());
        $this->assertSame(1, \App\Models\OperacionIdempotente::count());
    }

    public function test_f32_rollback_idempotencia_y_reintento(): void
    {
        $datos = $this->datos('100.0000', 'efectivo');

        try {
            $this->servicio->registrarConfirmado(
                $datos, $this->usuario, 'rollback-01'
            );

            $this->fail('Se permitio cobrar sin caja abierta.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }

        $this->assertSame(0, \App\Models\Pago::count());
        $this->assertSame(0, \App\Models\DetallePago::count());
        $this->assertSame(0, \App\Models\OperacionIdempotente::count());

        $this->abrirCaja();

        $pago = $this->servicio->registrarConfirmado(
            $datos, $this->usuario, 'rollback-01'
        );

        $this->assertSame('confirmado', $pago->estado);
        $this->assertSame(1, \App\Models\Pago::count());
        $this->assertSame(1, \App\Models\MovimientoCaja::count());
        $this->assertSame(1, \App\Models\OperacionIdempotente::count());
    }

    public function test_f33_reintento_recupera_estado_actual(): void
    {
        $datos = $this->datos('100.0000', 'transferencia');

        $pago = $this->servicio->registrarPendiente(
            $datos, $this->usuario, 'estado-actual-01'
        );

        $this->servicio->confirmar($pago->id, $this->usuario);

        $reintento = $this->servicio->registrarPendiente(
            $datos, $this->usuario, 'estado-actual-01'
        );

        $this->assertSame($pago->id, $reintento->id);
        $this->assertSame('confirmado', $reintento->estado);
        $this->assertSame(1, \App\Models\Pago::count());
    }

    public function test_f34_claves_distintas_permiten_pagos_distintos(): void
    {
        $datos = $this->datos('100.0000', 'transferencia');

        $primero = $this->servicio->registrarConfirmado(
            $datos, $this->usuario, 'pago-distinto-01'
        );

        $segundo = $this->servicio->registrarConfirmado(
            $datos, $this->usuario, 'pago-distinto-02'
        );

        $this->assertNotSame($primero->id, $segundo->id);
        $this->assertSame(2, \App\Models\Pago::count());
        $this->assertSame(2, \App\Models\OperacionIdempotente::count());
    }

    public function test_f35_normalizacion_idempotente(): void
    {
        $primero = $this->servicio->registrarPendiente(
            $this->datos('100', 'transferencia'),
            $this->usuario,
            'normalizacion-01'
        );

        $segundo = $this->servicio->registrarPendiente(
            $this->datos('100.0000', 'transferencia'),
            $this->usuario,
            'normalizacion-01'
        );

        $this->assertSame($primero->id, $segundo->id);
        $this->assertSame(1, \App\Models\Pago::count());
    }



    public function test_f36_reintento_revalida_permiso_registro(): void
    {
        $datos = $this->datos('100.0000', 'transferencia');

        $primero = $this->servicio->registrarConfirmado(
            $datos,
            $this->usuario,
            'f36-revocacion'
        );

        $permisoId = \Illuminate\Support\Facades\DB::table('permisos')
            ->where('clave', 'pagos.registrar')
            ->value('id');

        $eliminados = \Illuminate\Support\Facades\DB::table('rol_permiso')
            ->where('rol_id', $this->usuario->role_id)
            ->where('permiso_id', $permisoId)
            ->delete();

        $this->assertSame(1, $eliminados);

        try {
            $this->servicio->registrarConfirmado(
                $datos,
                $this->usuario,
                'f36-revocacion'
            );

            $this->fail('Se admitio un permiso revocado.');
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->assertNotNull($e);
        }

        $this->assertSame(
            'confirmado',
            $primero->refresh()->estado
        );

        $this->assertSame(
            1,
            \App\Models\Pago::query()
                ->where('referencia_tipo', 'venta')
                ->where('referencia_id', $this->venta->id)
                ->count()
        );

        $this->assertSame(
            1,
            \App\Models\OperacionIdempotente::query()
                ->where('clave', 'f36-revocacion')
                ->count()
        );
    }

}
