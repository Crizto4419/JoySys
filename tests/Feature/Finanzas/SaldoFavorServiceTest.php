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

final class SaldoFavorServiceTest extends TestCase
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


    private function saldos(): \App\Services\Finanzas\SaldoFavorService
    {
        return app(
            \App\Services\Finanzas\SaldoFavorService::class
        );
    }

    private function clientePrueba(): \App\Models\Cliente
    {
        return \App\Models\Cliente::create([
            'codigo' => 'SF-' . \Illuminate\Support\Str::uuid(),
            'nombre' => 'Cliente de prueba',
            'telefono' => null,
            'correo' => null,
            'activo' => true,
        ]);
    }

    private function credito(
        string $monto = '500.0000',
        ?\App\Models\Cliente $cliente = null,
        ?int $sucursalId = null
    ): \App\Models\SaldoFavor {
        $cliente ??= $this->clientePrueba();
        $sucursalId ??= $this->sucursal;

        $saldo = \App\Models\SaldoFavor::create([
            'cliente_id' => $cliente->id,
            'sucursal_id' => $sucursalId,
            'usuario_id' => $this->usuario->id,
            'moneda' => 'MXN',
            'saldo_disponible' => $monto,
            'estado' => 'activo',
        ]);

        // Fixture exclusivo de testing.
        // No representa una devolucion comercial real.
        \App\Models\MovimientoSaldoFavor::create([
            'saldo_favor_id' => $saldo->id,
            'sucursal_id' => $sucursalId,
            'usuario_id' => $this->usuario->id,
            'autorizado_por' => $this->usuario->id,
            'tipo_movimiento' => 'generacion',
            'monto' => $monto,
            'saldo_anterior' => '0.0000',
            'saldo_nuevo' => $monto,
            'aplicacion_saldo_favor_id' => null,
            'pago_id' => null,
            'devolucion_id' => null,
            'movimiento_original_id' => null,
        ]);

        return $saldo;
    }

    private function partidaCredito(
        \App\Models\SaldoFavor $saldo,
        string $monto = '100.0000',
        string $metodo = 'saldo_favor',
        string $estado = 'confirmado'
    ): \App\Models\DetallePago {
        $this->venta->forceFill([
            'cliente_id' => $saldo->cliente_id,
        ])->save();

        $pago = \App\Models\Pago::create([
            'sucursal_id' => $this->sucursal,
            'referencia_tipo' => 'venta',
            'referencia_id' => $this->venta->id,
            'pago_original_id' => null,
            'recibido_por' => $this->usuario->id,
            'monto_total' => $monto,
            'moneda' => 'MXN',
            'estado' => 'pendiente',
            'fecha_pago' => null,
        ]);

        $detalle = $pago->detalles()->create([
            'metodo_pago' => $metodo,
            'monto' => $monto,
            'referencia' => null,
            'detalle_adicional' => null,
            'detalle_pago_original_id' => null,
            'saldo_favor_id' => $metodo === 'saldo_favor'
                ? $saldo->id
                : null,
        ]);

        if ($estado !== 'pendiente') {
            $pago->forceFill([
                'estado' => $estado,
                'fecha_pago' => $estado === 'confirmado'
                    ? now()
                    : null,
            ])->save();
        }

        return $detalle->refresh();
    }

    private function aplicarCredito(
        \App\Models\SaldoFavor $saldo,
        string $monto = '100.0000'
    ): \App\Models\AplicacionSaldoFavor {
        $detalle = $this->partidaCredito($saldo, $monto);

        return $this->saldos()->aplicar(
            (int) $saldo->id,
            (int) $detalle->id,
            $this->usuario
        );
    }

    private function autorizador(): \App\Models\User
    {
        return $this->crearUsuario(
            $this->sucursal,
            ['pagos.ver', 'pagos.reembolsar']
        );
    }

    private function partidaReembolso(
        \App\Models\AplicacionSaldoFavor $aplicacion,
        \App\Models\User $usuario,
        string $monto = '50.0000'
    ): \App\Models\DetallePago {
        $original = $aplicacion->detallePago;
        $pagoOriginal = $original->pago;

        $pago = \App\Models\Pago::create([
            'sucursal_id' => $pagoOriginal->sucursal_id,
            'referencia_tipo' => $pagoOriginal->referencia_tipo,
            'referencia_id' => $pagoOriginal->referencia_id,
            'pago_original_id' => $pagoOriginal->id,
            'recibido_por' => $usuario->id,
            'monto_total' => $monto,
            'moneda' => 'MXN',
            'estado' => 'pendiente',
            'fecha_pago' => null,
        ]);

        $detalle = $pago->detalles()->create([
            'metodo_pago' => 'saldo_favor',
            'monto' => $monto,
            'referencia' => null,
            'detalle_adicional' => 'Reembolso de prueba',
            'detalle_pago_original_id' => $original->id,
            'saldo_favor_id' => $original->saldo_favor_id,
        ]);

        $pago->forceFill([
            'estado' => 'confirmado',
            'fecha_pago' => now(),
        ])->save();

        return $detalle->refresh();
    }

    public function test_sc01_consulta_disponibles(): void
    {
        $saldo = $this->credito('500.1234');

        $r = $this->saldos()->consultarDisponibles(
            (int) $saldo->cliente_id,
            $this->usuario
        );

        $this->assertSame('500.1234', $r['saldo_total']);
        $this->assertCount(1, $r['saldos']);
    }

    public function test_sc02_consulta_varios_creditos(): void
    {
        $cliente = $this->clientePrueba();

        $this->credito('100.1234', $cliente);
        $this->credito('200.4321', $cliente);

        $r = $this->saldos()->consultarDisponibles(
            (int) $cliente->id,
            $this->usuario
        );

        $this->assertSame('300.5555', $r['saldo_total']);
        $this->assertCount(2, $r['saldos']);
    }

    public function test_sc03_consulta_sin_creditos(): void
    {
        $cliente = $this->clientePrueba();

        $r = $this->saldos()->consultarDisponibles(
            (int) $cliente->id,
            $this->usuario
        );

        $this->assertSame('0.0000', $r['saldo_total']);
        $this->assertCount(0, $r['saldos']);
    }

    public function test_sc04_aplicacion_parcial(): void
    {
        $saldo = $this->credito('500');

        $a = $this->aplicarCredito($saldo, '125.1234');

        $this->assertSame(
            '374.8766',
            $saldo->refresh()->saldo_disponible
        );

        $this->assertSame('125.1234', $a->monto_aplicado);
        $this->assertSame('aplicada', $a->estado);
        $this->assertSame('activo', $saldo->estado);

        $this->assertDatabaseCount('movimientos_saldos_favor', 2);
        $this->assertDatabaseCount('movimientos_caja', 0);
    }

    public function test_sc05_aplicacion_total(): void
    {
        $saldo = $this->credito('500');

        $this->aplicarCredito($saldo, '500');

        $saldo->refresh();

        $this->assertSame('0.0000', $saldo->saldo_disponible);
        $this->assertSame('agotado', $saldo->estado);
    }

    public function test_sc06_rechaza_saldo_insuficiente(): void
    {
        $saldo = $this->credito('100');

        $this->expectException(
            \Illuminate\Validation\ValidationException::class
        );

        $this->aplicarCredito($saldo, '100.0001');
    }

    public function test_sc07_aplicacion_idempotente(): void
    {
        $saldo = $this->credito();

        $a = $this->aplicarCredito($saldo, '100');

        $b = $this->saldos()->aplicar(
            (int) $saldo->id,
            (int) $a->detalle_pago_id,
            $this->usuario
        );

        $this->assertSame($a->id, $b->id);

        $this->assertSame(
            '400.0000',
            $saldo->refresh()->saldo_disponible
        );

        $this->assertDatabaseCount('aplicaciones_saldos_favor', 1);
        $this->assertDatabaseCount('movimientos_saldos_favor', 2);
    }

    public function test_sc08_rechaza_otro_cliente(): void
    {
        $saldo = $this->credito();
        $otro = $this->credito();

        $detalle = $this->partidaCredito($otro);

        $this->expectException(
            \Illuminate\Validation\ValidationException::class
        );

        $this->saldos()->aplicar(
            (int) $saldo->id,
            (int) $detalle->id,
            $this->usuario
        );
    }

    public function test_sc09_rechaza_otra_sucursal(): void
    {
        $saldo = $this->credito(
            '500',
            null,
            $this->crearSucursal()
        );

        $detalle = $this->partidaCredito($saldo);

        $this->expectException(
            \Illuminate\Database\Eloquent\ModelNotFoundException::class
        );

        $this->saldos()->aplicar(
            (int) $saldo->id,
            (int) $detalle->id,
            $this->usuario
        );
    }

    public function test_sc10_rechaza_metodo_distinto(): void
    {
        $saldo = $this->credito();

        $detalle = $this->partidaCredito(
            $saldo,
            '100',
            'transferencia'
        );

        $this->expectException(
            \Illuminate\Validation\ValidationException::class
        );

        $this->saldos()->aplicar(
            (int) $saldo->id,
            (int) $detalle->id,
            $this->usuario
        );
    }

    public function test_sc11_rechaza_pago_pendiente(): void
    {
        $saldo = $this->credito();

        $detalle = $this->partidaCredito(
            $saldo,
            '100',
            'saldo_favor',
            'pendiente'
        );

        $this->expectException(
            \Illuminate\Validation\ValidationException::class
        );

        $this->saldos()->aplicar(
            (int) $saldo->id,
            (int) $detalle->id,
            $this->usuario
        );
    }

    public function test_sc12_rechaza_pago_de_otro_usuario(): void
    {
        $saldo = $this->credito();
        $detalle = $this->partidaCredito($saldo);

        $otro = $this->crearUsuario($this->sucursal);

        $this->expectException(
            \Illuminate\Validation\ValidationException::class
        );

        $this->saldos()->aplicar(
            (int) $saldo->id,
            (int) $detalle->id,
            $otro
        );
    }

    public function test_sc13_rechaza_duplicar_con_otro_credito(): void
    {
        $saldo = $this->credito();

        $otro = $this->credito(
            '500',
            $saldo->cliente
        );

        $a = $this->aplicarCredito($saldo);

        $this->expectException(
            \Illuminate\Validation\ValidationException::class
        );

        $this->saldos()->aplicar(
            (int) $otro->id,
            (int) $a->detalle_pago_id,
            $this->usuario
        );
    }

    public function test_sc14_rechaza_historial_inconsistente(): void
    {
        $saldo = $this->credito('500');

        $saldo->forceFill([
            'saldo_disponible' => '600',
        ])->save();

        $this->expectException(\LogicException::class);

        $this->aplicarCredito($saldo);
    }

    public function test_sc15_restitucion_parcial(): void
    {
        $saldo = $this->credito('500');
        $a = $this->aplicarCredito($saldo, '200');
        $usuario = $this->autorizador();

        $detalle = $this->partidaReembolso(
            $a,
            $usuario,
            '75.1234'
        );

        $m = $this->saldos()->restituir(
            (int) $detalle->id,
            $usuario
        );

        $this->assertSame('restitucion', $m->tipo_movimiento);

        $this->assertSame(
            '375.1234',
            $saldo->refresh()->saldo_disponible
        );

        $this->assertSame('aplicada', $a->refresh()->estado);
        $this->assertSame('200.0000', $a->monto_aplicado);
        $this->assertDatabaseCount('movimientos_caja', 0);
    }

    public function test_sc16_restitucion_total(): void
    {
        $saldo = $this->credito('500');
        $a = $this->aplicarCredito($saldo, '200');
        $usuario = $this->autorizador();

        $detalle = $this->partidaReembolso(
            $a,
            $usuario,
            '200'
        );

        $this->saldos()->restituir(
            (int) $detalle->id,
            $usuario
        );

        $this->assertSame(
            '500.0000',
            $saldo->refresh()->saldo_disponible
        );

        $this->assertSame('revertida', $a->refresh()->estado);
        $this->assertEquals($usuario->id, $a->revertida_por);
        $this->assertSame('200.0000', $a->monto_aplicado);
    }

    public function test_sc17_restituciones_acumuladas(): void
    {
        $saldo = $this->credito('500');
        $a = $this->aplicarCredito($saldo, '200');
        $usuario = $this->autorizador();

        foreach (['50', '75', '75'] as $monto) {
            $detalle = $this->partidaReembolso(
                $a,
                $usuario,
                $monto
            );

            $this->saldos()->restituir(
                (int) $detalle->id,
                $usuario
            );
        }

        $this->assertSame(
            '500.0000',
            $saldo->refresh()->saldo_disponible
        );

        $this->assertSame('revertida', $a->refresh()->estado);
        $this->assertDatabaseCount('movimientos_saldos_favor', 5);
    }

    public function test_sc18_reactiva_credito_agotado(): void
    {
        $saldo = $this->credito('100');
        $a = $this->aplicarCredito($saldo, '100');
        $usuario = $this->autorizador();

        $detalle = $this->partidaReembolso(
            $a,
            $usuario,
            '25'
        );

        $this->saldos()->restituir(
            (int) $detalle->id,
            $usuario
        );

        $this->assertSame('activo', $saldo->refresh()->estado);
        $this->assertSame('25.0000', $saldo->saldo_disponible);
    }

    public function test_sc19_restitucion_idempotente(): void
    {
        $saldo = $this->credito('500');
        $a = $this->aplicarCredito($saldo, '100');
        $usuario = $this->autorizador();

        $detalle = $this->partidaReembolso(
            $a,
            $usuario,
            '100'
        );

        $primero = $this->saldos()->restituir(
            (int) $detalle->id,
            $usuario
        );

        $segundo = $this->saldos()->restituir(
            (int) $detalle->id,
            $usuario
        );

        $this->assertSame($primero->id, $segundo->id);

        $this->assertSame(
            '500.0000',
            $saldo->refresh()->saldo_disponible
        );

        $this->assertDatabaseCount('movimientos_saldos_favor', 3);
    }

    public function test_sc20_rechaza_restitucion_excesiva(): void
    {
        $saldo = $this->credito();
        $a = $this->aplicarCredito($saldo, '100');
        $usuario = $this->autorizador();

        $detalle = $this->partidaReembolso(
            $a,
            $usuario,
            '100.0001'
        );

        $this->expectException(
            \Illuminate\Validation\ValidationException::class
        );

        $this->saldos()->restituir(
            (int) $detalle->id,
            $usuario
        );
    }

    public function test_sc21_rechaza_restitucion_sin_permiso(): void
    {
        $saldo = $this->credito();
        $a = $this->aplicarCredito($saldo);

        $detalle = $this->partidaReembolso(
            $a,
            $this->usuario
        );

        $this->expectException(
            \Illuminate\Auth\Access\AuthorizationException::class
        );

        $this->saldos()->restituir(
            (int) $detalle->id,
            $this->usuario
        );
    }

    public function test_sc22_rollback_aplicacion(): void
    {
        $saldo = $this->credito('500');
        $detalle = $this->partidaCredito($saldo, '100');

        $clase = \App\Models\MovimientoSaldoFavor::class;
        $dispatcher = $clase::getEventDispatcher();

        $clase::setEventDispatcher(clone $dispatcher);

        try {
            $clase::creating(function ($movimiento): void {
                if ($movimiento->tipo_movimiento === 'aplicacion') {
                    throw new \RuntimeException('Fallo controlado.');
                }
            });

            try {
                $this->saldos()->aplicar(
                    (int) $saldo->id,
                    (int) $detalle->id,
                    $this->usuario
                );

                $this->fail('Se esperaba un fallo.');
            } catch (\RuntimeException $e) {
                $this->assertSame(
                    'Fallo controlado.',
                    $e->getMessage()
                );
            }
        } finally {
            $clase::setEventDispatcher($dispatcher);
        }

        $this->assertDatabaseCount('aplicaciones_saldos_favor', 0);
        $this->assertDatabaseCount('movimientos_saldos_favor', 1);

        $this->assertSame(
            '500.0000',
            $saldo->refresh()->saldo_disponible
        );

        $this->saldos()->aplicar(
            (int) $saldo->id,
            (int) $detalle->id,
            $this->usuario
        );

        $this->assertSame(
            '400.0000',
            $saldo->refresh()->saldo_disponible
        );
    }

    public function test_sc23_movimiento_inmutable(): void
    {
        $saldo = $this->credito();

        $movimiento = $saldo->movimientos()->firstOrFail();

        $this->expectException(\LogicException::class);

        $movimiento->forceFill([
            'monto' => '100',
        ])->save();
    }

    public function test_sc24_movimiento_no_eliminable(): void
    {
        $saldo = $this->credito();

        $movimiento = $saldo->movimientos()->firstOrFail();

        $this->expectException(\LogicException::class);

        $movimiento->delete();
    }

    public function test_sc25_consulta_aislada_por_sucursal(): void
    {
        $cliente = $this->clientePrueba();

        $this->credito('100', $cliente);

        $this->credito(
            '900',
            $cliente,
            $this->crearSucursal()
        );

        $r = $this->saldos()->consultarDisponibles(
            (int) $cliente->id,
            $this->usuario
        );

        $this->assertSame('100.0000', $r['saldo_total']);
        $this->assertCount(1, $r['saldos']);
    }

    public function test_sc26_rechaza_aplicacion_sin_permiso(): void
    {
        $saldo = $this->credito();
        $detalle = $this->partidaCredito($saldo);

        $usuario = $this->crearUsuario(
            $this->sucursal,
            ['pagos.ver']
        );

        $this->expectException(
            \Illuminate\Auth\Access\AuthorizationException::class
        );

        $this->saldos()->aplicar(
            (int) $saldo->id,
            (int) $detalle->id,
            $usuario
        );
    }

    private function canceladorPrueba(): \App\Models\User
    {
        return $this->crearUsuario(
            $this->sucursal,
            [
                'pagos.ver',
                'pagos.registrar',
                'saldos_favor.cancelar',
            ]
        );
    }

    private function cancelarCreditoPrueba(
        \App\Models\SaldoFavor $saldo,
        \App\Models\User $usuario,
        string $clave = 'cancelacion-prueba-01',
        string $justificacion = 'Cancelacion administrativa autorizada'
    ): \App\Models\MovimientoSaldoFavor {
        return $this->saldos()->cancelar(
            (int) $saldo->getKey(),
            $justificacion,
            $clave,
            $usuario
        );
    }

    public function test_sc27_cancelacion_correcta(): void
    {
        $saldo = $this->credito();
        $usuario = $this->canceladorPrueba();

        $movimiento = $this->cancelarCreditoPrueba(
            $saldo,
            $usuario
        );

        $saldo->refresh();

        $this->assertSame('cancelado', $saldo->estado);
        $this->assertSame('0.0000', $saldo->saldo_disponible);
        $this->assertSame('cancelacion', $movimiento->tipo_movimiento);
        $this->assertSame('500.0000', $movimiento->monto);
        $this->assertSame('500.0000', $movimiento->saldo_anterior);
        $this->assertSame('0.0000', $movimiento->saldo_nuevo);

        $this->assertSame(
            (int) $usuario->getKey(),
            (int) $movimiento->autorizado_por
        );

        $this->assertDatabaseHas('operaciones_idempotentes', [
            'tipo_operacion' => 'cancelacion_saldo_favor',
            'clave' => 'cancelacion-prueba-01',
            'resultado_id' => $movimiento->getKey(),
        ]);
    }

    public function test_sc28_conserva_historial(): void
    {
        $saldo = $this->credito();

        $original = $saldo->movimientos()
            ->firstOrFail();

        $antes = $original->getRawOriginal();

        $this->cancelarCreditoPrueba(
            $saldo,
            $this->canceladorPrueba()
        );

        $despues = $original->refresh()->getRawOriginal();

        ksort($antes);
        ksort($despues);

        $this->assertSame($antes, $despues);
        $this->assertSame(2, $saldo->movimientos()->count());
    }

    public function test_sc29_justificacion_persistente(): void
    {
        $saldo = $this->credito();

        $movimiento = $this->cancelarCreditoPrueba(
            $saldo,
            $this->canceladorPrueba(),
            'cancelacion-sc29',
            '  Correccion administrativa autorizada  '
        );

        $this->assertSame(
            'Correccion administrativa autorizada',
            $movimiento->refresh()->justificacion
        );
    }

    public function test_sc30_cancelacion_idempotente(): void
    {
        $saldo = $this->credito();
        $usuario = $this->canceladorPrueba();

        $primero = $this->cancelarCreditoPrueba($saldo, $usuario);
        $segundo = $this->cancelarCreditoPrueba($saldo, $usuario);

        $this->assertSame(
            $primero->getKey(),
            $segundo->getKey()
        );

        $this->assertSame(
            1,
            $saldo->movimientos()
                ->where('tipo_movimiento', 'cancelacion')
                ->count()
        );

        $this->assertSame(
            1,
            \App\Models\OperacionIdempotente::query()
                ->where('tipo_operacion', 'cancelacion_saldo_favor')
                ->count()
        );
    }

    public function test_sc31_rechaza_reutilizacion_clave(): void
    {
        $saldo = $this->credito();
        $usuario = $this->canceladorPrueba();

        $this->cancelarCreditoPrueba($saldo, $usuario);

        try {
            $this->cancelarCreditoPrueba(
                $saldo,
                $usuario,
                'cancelacion-prueba-01',
                'Una justificacion diferente'
            );

            $this->fail('La clave reutilizada debio rechazarse.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }

        $this->assertSame(2, $saldo->movimientos()->count());
    }

    public function test_sc32_rechaza_doble_cancelacion(): void
    {
        $saldo = $this->credito();
        $usuario = $this->canceladorPrueba();

        $this->cancelarCreditoPrueba($saldo, $usuario);

        $this->errorValidacion(
            fn () => $this->cancelarCreditoPrueba(
                $saldo,
                $usuario,
                'cancelacion-distinta'
            ),
            'saldo_favor_id'
        );

        $this->assertSame(2, $saldo->movimientos()->count());

        $this->assertDatabaseMissing('operaciones_idempotentes', [
            'clave' => 'cancelacion-distinta',
        ]);
    }

    public function test_sc33_rechaza_usuario_sin_permiso(): void
    {
        $saldo = $this->credito();

        try {
            $this->cancelarCreditoPrueba(
                $saldo,
                $this->usuario
            );

            $this->fail('El usuario no debio cancelar.');
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->assertSame('activo', $saldo->refresh()->estado);
        }

        $this->assertSame(1, $saldo->movimientos()->count());
    }

    public function test_sc34_aislamiento_sucursal(): void
    {
        $saldo = $this->credito();

        $otraSucursal = $this->crearSucursal();

        $usuario = $this->crearUsuario(
            $otraSucursal,
            ['saldos_favor.cancelar']
        );

        try {
            $this->cancelarCreditoPrueba($saldo, $usuario);

            $this->fail('Se permitio cancelar un saldo ajeno.');
        } catch (
            \Illuminate\Database\Eloquent\ModelNotFoundException |
            \Illuminate\Validation\ValidationException |
            \Illuminate\Auth\Access\AuthorizationException $e
        ) {
            $this->assertSame('activo', $saldo->refresh()->estado);
        }

        $this->assertSame(1, $saldo->movimientos()->count());
    }

    public function test_sc35_rechaza_justificacion_vacia(): void
    {
        $saldo = $this->credito();

        $this->errorValidacion(
            fn () => $this->cancelarCreditoPrueba(
                $saldo,
                $this->canceladorPrueba(),
                'cancelacion-sc35',
                '   '
            ),
            'justificacion'
        );

        $this->assertSame('activo', $saldo->refresh()->estado);
        $this->assertSame(1, $saldo->movimientos()->count());
    }

    public function test_sc36_rechaza_justificacion_larga(): void
    {
        $saldo = $this->credito();

        $this->errorValidacion(
            fn () => $this->cancelarCreditoPrueba(
                $saldo,
                $this->canceladorPrueba(),
                'cancelacion-sc36',
                str_repeat('A', 256)
            ),
            'justificacion'
        );

        $this->assertSame('activo', $saldo->refresh()->estado);
    }

    public function test_sc37_rechaza_saldo_agotado(): void
    {
        $saldo = $this->credito();

        $this->aplicarCredito($saldo, '500.0000');

        $this->assertSame('agotado', $saldo->refresh()->estado);

        $this->errorValidacion(
            fn () => $this->cancelarCreditoPrueba(
                $saldo,
                $this->canceladorPrueba()
            ),
            'saldo_favor_id'
        );

        $this->assertSame('agotado', $saldo->refresh()->estado);
    }

    public function test_sc38_rechaza_aplicacion_pendiente(): void
    {
        $saldo = $this->credito();

        $this->aplicarCredito($saldo, '100.0000');

        $this->errorValidacion(
            fn () => $this->cancelarCreditoPrueba(
                $saldo,
                $this->canceladorPrueba()
            ),
            'saldo_favor_id'
        );

        $this->assertSame(
            '400.0000',
            $saldo->refresh()->saldo_disponible
        );

        $this->assertSame('activo', $saldo->estado);
    }

    public function test_sc39_rechaza_restitucion_parcial(): void
    {
        $saldo = $this->credito();

        $aplicacion = $this->aplicarCredito(
            $saldo,
            '100.0000'
        );

        $autorizador = $this->autorizador();

        $detalle = $this->partidaReembolso(
            $aplicacion,
            $autorizador,
            '40.0000'
        );

        $this->saldos()->restituir(
            (int) $detalle->getKey(),
            $autorizador
        );

        $this->errorValidacion(
            fn () => $this->cancelarCreditoPrueba(
                $saldo,
                $this->canceladorPrueba()
            ),
            'saldo_favor_id'
        );

        $this->assertSame(
            '440.0000',
            $saldo->refresh()->saldo_disponible
        );
    }

    public function test_sc40_cancela_saldo_restituido(): void
    {
        $saldo = $this->credito();

        $aplicacion = $this->aplicarCredito(
            $saldo,
            '100.0000'
        );

        $autorizador = $this->autorizador();

        $detalle = $this->partidaReembolso(
            $aplicacion,
            $autorizador,
            '100.0000'
        );

        $this->saldos()->restituir(
            (int) $detalle->getKey(),
            $autorizador
        );

        $this->assertSame(
            'revertida',
            $aplicacion->refresh()->estado
        );

        $movimiento = $this->cancelarCreditoPrueba(
            $saldo,
            $this->canceladorPrueba()
        );

        $this->assertSame('500.0000', $movimiento->monto);
        $this->assertSame('cancelado', $saldo->refresh()->estado);
        $this->assertSame('0.0000', $saldo->saldo_disponible);
        $this->assertSame(4, $saldo->movimientos()->count());
    }

    public function test_sc41_rollback_cancelacion(): void
    {
        $saldo = $this->credito();
        $usuario = $this->canceladorPrueba();

        $dispatcher = \App\Models\SaldoFavor::getEventDispatcher();

        $this->assertNotNull($dispatcher);

        \App\Models\SaldoFavor::setEventDispatcher(
            clone $dispatcher
        );

        \App\Models\SaldoFavor::updating(
            static function (\App\Models\SaldoFavor $modelo): void {
                if ($modelo->estado === 'cancelado') {
                    throw new \RuntimeException(
                        'Fallo inducido de cancelacion'
                    );
                }
            }
        );

        try {
            $this->cancelarCreditoPrueba(
                $saldo,
                $usuario,
                'cancelacion-rollback'
            );

            $this->fail('El fallo inducido no fue ejecutado.');
        } catch (\RuntimeException $e) {
            $this->assertSame(
                'Fallo inducido de cancelacion',
                $e->getMessage()
            );
        } finally {
            \App\Models\SaldoFavor::setEventDispatcher($dispatcher);
        }

        $this->assertSame('activo', $saldo->refresh()->estado);
        $this->assertSame('500.0000', $saldo->saldo_disponible);
        $this->assertSame(1, $saldo->movimientos()->count());

        $this->assertDatabaseMissing('operaciones_idempotentes', [
            'clave' => 'cancelacion-rollback',
        ]);

        $movimiento = $this->cancelarCreditoPrueba(
            $saldo,
            $usuario,
            'cancelacion-rollback'
        );

        $this->assertSame('cancelacion', $movimiento->tipo_movimiento);
        $this->assertSame('cancelado', $saldo->refresh()->estado);
    }

    public function test_sc42_cancelacion_sin_movimientos_caja(): void
    {
        $saldo = $this->credito();

        $antes = \Illuminate\Support\Facades\DB::table(
            'movimientos_caja'
        )->count();

        $pagosAntes = \Illuminate\Support\Facades\DB::table(
            'pagos'
        )->count();

        $this->cancelarCreditoPrueba(
            $saldo,
            $this->canceladorPrueba()
        );

        $this->assertSame(
            $antes,
            \Illuminate\Support\Facades\DB::table(
                'movimientos_caja'
            )->count()
        );

        $this->assertSame(
            $pagosAntes,
            \Illuminate\Support\Facades\DB::table(
                'pagos'
            )->count()
        );

        $this->assertSame('cancelado', $saldo->refresh()->estado);
    }
}
