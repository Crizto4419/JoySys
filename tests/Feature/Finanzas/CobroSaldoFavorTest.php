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

final class CobroSaldoFavorTest extends TestCase
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


    private function creditoPrueba(
        string $monto = '500.0000',
        ?\App\Models\Cliente $cliente = null,
        ?int $sucursalId = null
    ): \App\Models\SaldoFavor {
        $sucursalId ??= $this->sucursal;

        $cliente ??= \App\Models\Cliente::create([
            'codigo' => 'CSF-'.\Illuminate\Support\Str::uuid(),
            'nombre' => 'Cliente de prueba',
            'activo' => true,
        ]);

        $this->venta->forceFill([
            'cliente_id' => $cliente->id,
        ])->save();

        $saldo = \App\Models\SaldoFavor::create([
            'cliente_id' => $cliente->id,
            'sucursal_id' => $sucursalId,
            'usuario_id' => $this->usuario->id,
            'moneda' => 'MXN',
            'saldo_disponible' => $monto,
            'estado' => 'activo',
        ]);

        \App\Models\MovimientoSaldoFavor::create([
            'saldo_favor_id' => $saldo->id,
            'sucursal_id' => $sucursalId,
            'usuario_id' => $this->usuario->id,
            'autorizado_por' => $this->usuario->id,
            'tipo_movimiento' => 'generacion',
            'monto' => $monto,
            'saldo_anterior' => '0.0000',
            'saldo_nuevo' => $monto,
        ]);

        return $saldo;
    }

    private function datosCredito(
        \App\Models\SaldoFavor $saldo,
        string $monto = '100.0000'
    ): array {
        $datos = $this->datos($monto, 'saldo_favor');

        $datos['detalles'][0]['saldo_favor_id'] = $saldo->id;

        return $datos;
    }

    private function cantidadAplicaciones(): int
    {
        return \App\Models\AplicacionSaldoFavor::count();
    }

    public function test_cs01_pendiente_conserva_credito(): void
    {
        $saldo = $this->creditoPrueba();

        $pago = $this->servicio->registrarPendiente(
            $this->datosCredito($saldo),
            $this->usuario
        );

        $this->assertSame('pendiente', $pago->estado);

        $this->assertSame(
            (int) $saldo->id,
            (int) $pago->detalles->first()->saldo_favor_id
        );

        $this->assertSame(
            '500.0000',
            $saldo->refresh()->saldo_disponible
        );

        $this->assertSame(0, $this->cantidadAplicaciones());
    }

    public function test_cs02_confirmacion_descontada(): void
    {
        $saldo = $this->creditoPrueba();

        $pago = $this->servicio->registrarPendiente(
            $this->datosCredito($saldo),
            $this->usuario
        );

        $confirmado = $this->servicio->confirmar(
            $pago->id,
            $this->usuario
        );

        $this->assertSame('confirmado', $confirmado->estado);

        $this->assertSame(
            '400.0000',
            $saldo->refresh()->saldo_disponible
        );

        $this->assertSame(1, $this->cantidadAplicaciones());

        $this->assertSame(
            0,
            \App\Models\MovimientoCaja::count()
        );
    }

    public function test_cs03_confirmacion_directa(): void
    {
        $saldo = $this->creditoPrueba();

        $pago = $this->servicio->registrarConfirmado(
            $this->datosCredito($saldo, '500.0000'),
            $this->usuario
        );

        $this->assertSame('confirmado', $pago->estado);
        $this->assertSame('agotado', $saldo->refresh()->estado);

        $this->assertSame(
            '0.0000',
            $saldo->saldo_disponible
        );
    }

    public function test_cs04_confirmacion_repetida(): void
    {
        $saldo = $this->creditoPrueba();

        $pago = $this->servicio->registrarConfirmado(
            $this->datosCredito($saldo, '500.0000'),
            $this->usuario
        );

        $repetido = $this->servicio->confirmar(
            $pago->id,
            $this->usuario
        );

        $this->assertSame($pago->id, $repetido->id);
        $this->assertSame(1, $this->cantidadAplicaciones());

        $this->assertSame(
            '0.0000',
            $saldo->refresh()->saldo_disponible
        );
    }

    public function test_cs05_cancelacion_pendiente(): void
    {
        $saldo = $this->creditoPrueba();

        $pago = $this->servicio->registrarPendiente(
            $this->datosCredito($saldo),
            $this->usuario
        );

        $cancelado = $this->servicio->cancelar(
            $pago->id,
            $this->usuario
        );

        $this->assertSame('cancelado', $cancelado->estado);

        $this->assertSame(
            '500.0000',
            $saldo->refresh()->saldo_disponible
        );

        $this->assertSame(0, $this->cantidadAplicaciones());
    }

    public function test_cs06_pago_mixto(): void
    {
        $saldo = $this->creditoPrueba();

        $this->abrirCaja();

        $datos = $this->datos('300.0000');

        $datos['detalles'] = [
            [
                'metodo_pago' => 'saldo_favor',
                'saldo_favor_id' => $saldo->id,
                'monto' => '100.0000',
            ],
            [
                'metodo_pago' => 'efectivo',
                'monto' => '100.0000',
            ],
            [
                'metodo_pago' => 'transferencia',
                'monto' => '100.0000',
            ],
        ];

        $pago = $this->servicio->registrarConfirmado(
            $datos,
            $this->usuario
        );

        $this->assertSame('confirmado', $pago->estado);
        $this->assertSame(3, $pago->detalles->count());

        $this->assertSame(
            '400.0000',
            $saldo->refresh()->saldo_disponible
        );

        $this->assertSame(
            1,
            \App\Models\MovimientoCaja::count()
        );

        $this->assertSame(
            '100.0000',
            \App\Models\MovimientoCaja::first()->monto
        );
    }

    public function test_cs07_revalida_disponibilidad(): void
    {
        $saldo = $this->creditoPrueba('100.0000');

        $primero = $this->servicio->registrarPendiente(
            $this->datosCredito($saldo),
            $this->usuario
        );

        $segundo = $this->servicio->registrarPendiente(
            $this->datosCredito($saldo),
            $this->usuario
        );

        $this->servicio->confirmar(
            $primero->id,
            $this->usuario
        );

        $this->errorValidacion(
            fn () => $this->servicio->confirmar(
                $segundo->id,
                $this->usuario
            ),
            'detalles'
        );

        $this->assertSame(
            'pendiente',
            $segundo->refresh()->estado
        );

        $this->assertSame(1, $this->cantidadAplicaciones());
    }

    public function test_cs08_rollback_pago_mixto(): void
    {
        $saldo = $this->creditoPrueba();

        $datos = $this->datos('200.0000');

        $datos['detalles'] = [
            [
                'metodo_pago' => 'saldo_favor',
                'saldo_favor_id' => $saldo->id,
                'monto' => '100.0000',
            ],
            [
                'metodo_pago' => 'efectivo',
                'monto' => '100.0000',
            ],
        ];

        $pagosAntes = \App\Models\Pago::count();
        $detallesAntes = \App\Models\DetallePago::count();

        $fallo = false;

        try {
            $this->servicio->registrarConfirmado(
                $datos,
                $this->usuario
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            $fallo = true;
        }

        $this->assertTrue($fallo);

        $this->assertSame(
            $pagosAntes,
            \App\Models\Pago::count()
        );

        $this->assertSame(
            $detallesAntes,
            \App\Models\DetallePago::count()
        );

        $this->assertSame(
            '500.0000',
            $saldo->refresh()->saldo_disponible
        );

        $this->assertSame(0, $this->cantidadAplicaciones());

        $this->assertSame(
            1,
            $saldo->movimientos()->count()
        );
    }

    public function test_cs09_varios_creditos(): void
    {
        $primero = $this->creditoPrueba('300.0000');

        $segundo = $this->creditoPrueba(
            '200.0000',
            $primero->cliente
        );

        $datos = $this->datos('250.0000');

        $datos['detalles'] = [
            [
                'metodo_pago' => 'saldo_favor',
                'saldo_favor_id' => $primero->id,
                'monto' => '150.0000',
            ],
            [
                'metodo_pago' => 'saldo_favor',
                'saldo_favor_id' => $segundo->id,
                'monto' => '100.0000',
            ],
        ];

        $pago = $this->servicio->registrarConfirmado(
            $datos,
            $this->usuario
        );

        $this->assertSame('confirmado', $pago->estado);

        $this->assertSame(
            '150.0000',
            $primero->refresh()->saldo_disponible
        );

        $this->assertSame(
            '100.0000',
            $segundo->refresh()->saldo_disponible
        );

        $this->assertSame(2, $this->cantidadAplicaciones());
    }

    public function test_cs10_rechaza_credito_duplicado(): void
    {
        $saldo = $this->creditoPrueba();

        $datos = $this->datosCredito($saldo, '200.0000');

        $datos['detalles'] = [
            [
                'metodo_pago' => 'saldo_favor',
                'saldo_favor_id' => $saldo->id,
                'monto' => '100.0000',
            ],
            [
                'metodo_pago' => 'saldo_favor',
                'saldo_favor_id' => $saldo->id,
                'monto' => '100.0000',
            ],
        ];

        $this->errorValidacion(
            fn () => $this->servicio->registrarPendiente(
                $datos,
                $this->usuario
            ),
            'detalles'
        );

        $this->assertSame(0, $this->cantidadAplicaciones());

        $this->assertSame(
            '500.0000',
            $saldo->refresh()->saldo_disponible
        );
    }

    public function test_cs11_rechaza_credito_otro_cliente(): void
    {
        $saldo = $this->creditoPrueba();

        $otro = $this->creditoPrueba();

        $this->assertNotSame(
            $saldo->cliente_id,
            $otro->cliente_id
        );

        $this->errorValidacion(
            fn () => $this->servicio->registrarPendiente(
                $this->datosCredito($saldo),
                $this->usuario
            ),
            'detalles'
        );

        $this->assertSame(0, $this->cantidadAplicaciones());
    }

    public function test_cs12_rechaza_credito_otra_sucursal(): void
    {
        $otraSucursal = $this->crearSucursal();

        $saldo = $this->creditoPrueba(
            '500.0000',
            null,
            $otraSucursal
        );

        $this->errorValidacion(
            fn () => $this->servicio->registrarPendiente(
                $this->datosCredito($saldo),
                $this->usuario
            ),
            'detalles'
        );

        $this->assertSame(0, $this->cantidadAplicaciones());
    }

    public function test_cs13_rechaza_cliente_inactivo(): void
    {
        $saldo = $this->creditoPrueba();

        $saldo->cliente->forceFill([
            'activo' => false,
        ])->save();

        $this->errorValidacion(
            fn () => $this->servicio->registrarPendiente(
                $this->datosCredito($saldo),
                $this->usuario
            ),
            'detalles'
        );
    }

    public function test_cs14_rechaza_credito_en_efectivo(): void
    {
        $saldo = $this->creditoPrueba();

        $datos = $this->datos('100.0000', 'efectivo');

        $datos['detalles'][0]['saldo_favor_id'] = $saldo->id;

        $this->errorValidacion(
            fn () => $this->servicio->registrarPendiente(
                $datos,
                $this->usuario
            ),
            'detalles'
        );
    }

    public function test_cs15_rechaza_credito_insuficiente(): void
    {
        $saldo = $this->creditoPrueba('50.0000');

        $this->errorValidacion(
            fn () => $this->servicio->registrarPendiente(
                $this->datosCredito($saldo, '100.0000'),
                $this->usuario
            ),
            'detalles'
        );

        $this->assertSame(
            '50.0000',
            $saldo->refresh()->saldo_disponible
        );
    }

    public function test_cs16_rollback_inducido_tras_aplicacion(): void
    {
        $saldo = $this->creditoPrueba();

        $this->abrirCaja();

        $datos = $this->datos('200.0000');

        $datos['detalles'] = [
            [
                'metodo_pago' => 'saldo_favor',
                'saldo_favor_id' => $saldo->id,
                'monto' => '100.0000',
            ],
            [
                'metodo_pago' => 'efectivo',
                'monto' => '100.0000',
            ],
        ];

        $pagosAntes = \App\Models\Pago::count();

        $detallesAntes = \App\Models\DetallePago::count();

        $dispatcherOriginal =
            \App\Models\MovimientoCaja::getEventDispatcher();

        $this->assertNotNull($dispatcherOriginal);

        $dispatcherPrueba = clone $dispatcherOriginal;

        $descuentoObservado = false;

        \App\Models\MovimientoCaja::setEventDispatcher(
            $dispatcherPrueba
        );

        try {
            \App\Models\MovimientoCaja::creating(
                function () use (
                    $saldo,
                    &$descuentoObservado
                ) {
                    $this->assertSame(
                        '400.0000',
                        $saldo->fresh()->saldo_disponible
                    );

                    $this->assertSame(
                        1,
                        $this->cantidadAplicaciones()
                    );

                    $descuentoObservado = true;

                    throw new \RuntimeException(
                        'FALLO_INDUCIDO_CS16'
                    );
                }
            );

            try {
                $this->servicio->registrarConfirmado(
                    $datos,
                    $this->usuario
                );

                $this->fail('Se esperaba un fallo inducido.');

            } catch (\RuntimeException $e) {
                $this->assertSame(
                    'FALLO_INDUCIDO_CS16',
                    $e->getMessage()
                );
            }

        } finally {
            \App\Models\MovimientoCaja::setEventDispatcher(
                $dispatcherOriginal
            );
        }

        $this->assertTrue($descuentoObservado);

        $this->assertSame(
            $pagosAntes,
            \App\Models\Pago::count()
        );

        $this->assertSame(
            $detallesAntes,
            \App\Models\DetallePago::count()
        );

        $this->assertSame(
            '500.0000',
            $saldo->refresh()->saldo_disponible
        );

        $this->assertSame(0, $this->cantidadAplicaciones());

        $this->assertSame(
            1,
            $saldo->movimientos()->count()
        );

        $this->assertSame(
            0,
            \App\Models\MovimientoCaja::count()
        );

        $reintento = $this->servicio->registrarConfirmado(
            $datos,
            $this->usuario
        );

        $this->assertSame('confirmado', $reintento->estado);

        $this->assertSame(
            '400.0000',
            $saldo->refresh()->saldo_disponible
        );

        $this->assertSame(1, $this->cantidadAplicaciones());
    }



    public function test_cs17_cobro_credito_idempotente(): void
    {
        $saldo = $this->creditoPrueba('500.0000');
        $datos = $this->datosCredito($saldo, '100.0000');

        $servicio = app(\App\Services\Finanzas\PagoService::class);

        $primero = $servicio->registrarConfirmado(
            $datos,
            $this->usuario,
            'cs17-cobro-credito'
        );

        $segundo = $servicio->registrarConfirmado(
            $datos,
            $this->usuario,
            'cs17-cobro-credito'
        );

        $this->assertSame($primero->id, $segundo->id);

        $this->assertSame(
            '400.0000',
            $saldo->refresh()->saldo_disponible
        );

        $this->assertSame(
            1,
            \App\Models\AplicacionSaldoFavor::query()
                ->where('saldo_favor_id', $saldo->id)
                ->count()
        );

        $this->assertSame(
            1,
            \App\Models\MovimientoSaldoFavor::query()
                ->where('saldo_favor_id', $saldo->id)
                ->where('tipo_movimiento', 'aplicacion')
                ->count()
        );

        $this->assertSame(
            1,
            \App\Models\OperacionIdempotente::query()
                ->where('clave', 'cs17-cobro-credito')
                ->count()
        );
    }

    public function test_cs18_cobro_mixto_idempotente(): void
    {
        $this->abrirCaja();

        $saldo = $this->creditoPrueba('500.0000');
        $datos = $this->datosCredito($saldo, '100.0000');

        $datos['monto_total'] = '150.0000';

        $datos['detalles'][] = [
            'metodo_pago' => 'efectivo',
            'monto' => '50.0000',
        ];

        $servicio = app(\App\Services\Finanzas\PagoService::class);

        $primero = $servicio->registrarConfirmado(
            $datos,
            $this->usuario,
            'cs18-cobro-mixto'
        );

        $segundo = $servicio->registrarConfirmado(
            $datos,
            $this->usuario,
            'cs18-cobro-mixto'
        );

        $this->assertSame($primero->id, $segundo->id);

        $this->assertSame(
            '400.0000',
            $saldo->refresh()->saldo_disponible
        );

        $this->assertSame(2, $primero->detalles()->count());

        $this->assertSame(
            1,
            \App\Models\AplicacionSaldoFavor::query()
                ->where('saldo_favor_id', $saldo->id)
                ->count()
        );

        $movimientos = \App\Models\MovimientoCaja::query()
            ->whereIn(
                'detalle_pago_id',
                $primero->detalles()->pluck('id')
            )
            ->get();

        $this->assertCount(1, $movimientos);
        $this->assertSame('ingreso', $movimientos->first()->tipo_movimiento);
        $this->assertSame('50.0000', $movimientos->first()->monto);
    }

    public function test_cs19_fallo_mixto_no_deja_idempotencia(): void
    {
        $saldo = $this->creditoPrueba('500.0000');
        $datos = $this->datosCredito($saldo, '100.0000');

        $datos['monto_total'] = '150.0000';

        $datos['detalles'][] = [
            'metodo_pago' => 'efectivo',
            'monto' => '50.0000',
        ];

        $servicio = app(\App\Services\Finanzas\PagoService::class);

        $pagosAntes = \App\Models\Pago::query()->count();
        $detallesAntes = \App\Models\DetallePago::query()->count();

        try {
            $servicio->registrarConfirmado(
                $datos,
                $this->usuario,
                'cs19-reintento-mixto'
            );

            $this->fail('Se admitio efectivo sin caja abierta.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }

        $this->assertSame(
            '500.0000',
            $saldo->refresh()->saldo_disponible
        );

        $this->assertSame(
            $pagosAntes,
            \App\Models\Pago::query()->count()
        );

        $this->assertSame(
            $detallesAntes,
            \App\Models\DetallePago::query()->count()
        );

        $this->assertSame(
            0,
            \App\Models\AplicacionSaldoFavor::query()
                ->where('saldo_favor_id', $saldo->id)
                ->count()
        );

        $this->assertFalse(
            \App\Models\OperacionIdempotente::query()
                ->where('clave', 'cs19-reintento-mixto')
                ->exists()
        );

        $this->abrirCaja();

        $pago = $servicio->registrarConfirmado(
            $datos,
            $this->usuario,
            'cs19-reintento-mixto'
        );

        $this->assertSame('confirmado', $pago->estado);

        $this->assertSame(
            '400.0000',
            $saldo->refresh()->saldo_disponible
        );

        $this->assertSame(
            1,
            \App\Models\OperacionIdempotente::query()
                ->where('clave', 'cs19-reintento-mixto')
                ->count()
        );
    }

}
