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
use App\Services\Finanzas\ReembolsoService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class ReembolsoServiceTest extends TestCase
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
            'pagos.reembolsar',
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

    private function original(
        string $monto = '100',
        string $metodo = 'transferencia'
    ): Pago {
        return $this->servicio->registrarConfirmado(
            $this->datos($monto, $metodo),
            $this->usuario
        );
    }

    private function solicitud(
        Pago $pago,
        string $monto = '50'
    ): array {
        return [
            'justificacion' => 'Reembolso autorizado',
            'detalles' => [
                [
                    'detalle_pago_original_id' =>
                        $pago->detalles->first()->getKey(),
                    'monto' => $monto,
                ],
            ],
        ];
    }

    private function reembolsar(
        Pago $pago,
        string $monto = '50',
        ?User $usuario = null
    ): Pago {
        return app(ReembolsoService::class)->registrar(
            $pago->getKey(),
            $this->solicitud($pago, $monto),
            $usuario ?? $this->usuario
        );
    }

    public function test_rf01_reembolso_parcial(): void
    {
        $original = $this->original();
        $reembolso = $this->reembolsar($original);

        $this->assertSame('confirmado', $reembolso->estado);
        $this->assertSame('50.0000', $reembolso->monto_total);
        $this->assertEquals(
            $original->getKey(),
            $reembolso->pago_original_id
        );
        $this->assertSame(2, Pago::count());
        $this->assertSame(0, MovimientoCaja::count());
    }

    public function test_rf02_reembolso_total(): void
    {
        $original = $this->original();
        $reembolso = $this->reembolsar($original, '100');

        $this->assertSame('100.0000', $reembolso->monto_total);

        $this->errorValidacion(
            fn () => $this->reembolsar($original, '0.0001'),
            'monto'
        );

        $this->assertSame(2, Pago::count());
    }

    public function test_rf03_acumulacion_parcial(): void
    {
        $original = $this->original();

        $this->reembolsar($original, '25');
        $this->reembolsar($original, '75');

        $this->errorValidacion(
            fn () => $this->reembolsar($original, '1'),
            'monto'
        );

        $this->assertSame(3, Pago::count());
    }

    public function test_rf04_rechaza_exceso(): void
    {
        $original = $this->original();

        $this->errorValidacion(
            fn () => $this->reembolsar($original, '100.0001'),
            'monto'
        );

        $this->assertSame(1, Pago::count());
        $this->assertSame(1, DetallePago::count());
    }

    public function test_rf05_preserva_original(): void
    {
        $original = $this->original();

        $antes = $original->getRawOriginal();
        $detalle = $original->detalles->first();
        $antesDetalle = $detalle->getRawOriginal();

        $this->reembolsar($original);

        $this->assertSame(
            $antes,
            $original->refresh()->getRawOriginal()
        );

        $this->assertSame(
            $antesDetalle,
            $detalle->refresh()->getRawOriginal()
        );
    }

    public function test_rf06_conserva_metodo_y_partida(): void
    {
        $original = $this->original('100', 'tarjeta_debito');
        $reembolso = $this->reembolsar($original);

        $detalle = $reembolso->detalles->first();

        $this->assertSame('tarjeta_debito', $detalle->metodo_pago);
        $this->assertEquals(
            $original->detalles->first()->getKey(),
            $detalle->detalle_pago_original_id
        );

        $this->assertSame(
            'Reembolso autorizado',
            $detalle->detalle_adicional
        );
    }

    public function test_rf07_rechaza_pendiente(): void
    {
        $original = $this->pendiente();

        $this->errorValidacion(
            fn () => $this->reembolsar($original),
            'pago'
        );
    }

    public function test_rf08_rechaza_cancelado(): void
    {
        $original = $this->pendiente();

        $this->servicio->cancelar(
            $original->getKey(),
            $this->usuario
        );

        $this->errorValidacion(
            fn () => $this->reembolsar($original),
            'pago'
        );
    }

    public function test_rf09_rechaza_reembolso_de_reembolso(): void
    {
        $original = $this->original();
        $reembolso = $this->reembolsar($original);

        $this->errorValidacion(
            fn () => $this->reembolsar($reembolso, '1'),
            'pago'
        );
    }

    public function test_rf10_partida_ajena(): void
    {
        $original = $this->original();
        $otro = $this->original();

        $this->errorValidacion(
            fn () => app(ReembolsoService::class)->registrar(
                $original->getKey(),
                $this->solicitud($otro),
                $this->usuario
            ),
            'detalles'
        );

        $this->assertSame(2, Pago::count());
    }

    public function test_rf11_permiso_independiente(): void
    {
        $original = $this->original();

        $otro = $this->crearUsuario(
            $this->sucursal,
            ['pagos.ver', 'pagos.registrar']
        );

        $this->expectException(AuthorizationException::class);

        $this->reembolsar($original, '50', $otro);
    }

    public function test_rf12_autorizado_misma_sucursal(): void
    {
        $original = $this->original();

        $otro = $this->crearUsuario(
            $this->sucursal,
            ['pagos.reembolsar']
        );

        $reembolso = $this->reembolsar($original, '50', $otro);

        $this->assertEquals(
            $otro->getKey(),
            $reembolso->recibido_por
        );
    }

    public function test_rf13_aislamiento_sucursal(): void
    {
        $original = $this->original();

        $otro = $this->crearUsuario(
            $this->crearSucursal(),
            ['pagos.reembolsar']
        );

        $this->expectException(ModelNotFoundException::class);

        $this->reembolsar($original, '50', $otro);
    }

    public function test_rf14_reembolso_efectivo(): void
    {
        $this->abrirCaja();

        $original = $this->original('100', 'efectivo');
        $reembolso = $this->reembolsar($original);

        $movimiento = $reembolso->detalles->first()->movimientoCaja;

        $this->assertNotNull($movimiento);
        $this->assertSame('reembolso', $movimiento->tipo_movimiento);
        $this->assertSame('50.0000', $movimiento->monto);
        $this->assertEquals(
            $this->usuario->getKey(),
            $movimiento->autorizado_por
        );

        $this->assertNull($movimiento->movimiento_original_id);
        $this->assertSame(2, MovimientoCaja::count());
    }

    public function test_rf15_efectivo_sin_caja(): void
    {
        $this->abrirCaja();

        $original = $this->original('100', 'efectivo');

        app(SesionCajaService::class)->cerrar([
            'efectivo_contado' => '600',
        ], $this->usuario);

        $this->errorValidacion(
            fn () => $this->reembolsar($original),
            'sesion_caja'
        );

        $this->assertSame(1, Pago::count());
        $this->assertSame(1, MovimientoCaja::count());
    }

    public function test_rf16_sesion_posterior(): void
    {
        $this->abrirCaja();

        $original = $this->original('100', 'efectivo');

        $sesion = app(SesionCajaService::class)->cerrar([
            'efectivo_contado' => '600',
        ], $this->usuario);

        $nueva = app(SesionCajaService::class)->abrir([
            'caja_id' => $sesion->caja_id,
            'fondo_inicial' => '100',
        ], $this->usuario);

        $reembolso = $this->reembolsar($original);

        $this->assertEquals(
            $nueva->getKey(),
            $reembolso->detalles->first()->movimientoCaja->sesion_caja_id
        );
    }

    public function test_rf17_efectivo_insuficiente(): void
    {
        $this->abrirCaja();

        $original = $this->original('100', 'efectivo');

        $sesion = app(SesionCajaService::class)->cerrar([
            'efectivo_contado' => '600',
        ], $this->usuario);

        app(SesionCajaService::class)->abrir([
            'caja_id' => $sesion->caja_id,
            'fondo_inicial' => '10',
        ], $this->usuario);

        $this->errorValidacion(
            fn () => $this->reembolsar($original),
            'monto'
        );

        $this->assertSame(1, Pago::count());
        $this->assertSame(1, DetallePago::count());
        $this->assertSame(1, MovimientoCaja::count());
    }

    public function test_rf18_reembolso_mixto(): void
    {
        $this->abrirCaja();

        $datos = $this->datos('300');

        $datos['detalles'] = [
            ['metodo_pago' => 'efectivo', 'monto' => '100'],
            ['metodo_pago' => 'transferencia', 'monto' => '200'],
        ];

        $original = $this->servicio->registrarConfirmado(
            $datos,
            $this->usuario
        );

        $solicitud = [
            'justificacion' => 'Reembolso mixto autorizado',
            'detalles' => [],
        ];

        foreach ($original->detalles as $detalle) {
            $solicitud['detalles'][] = [
                'detalle_pago_original_id' => $detalle->getKey(),
                'monto' => $detalle->monto,
            ];
        }

        $reembolso = app(ReembolsoService::class)->registrar(
            $original->getKey(),
            $solicitud,
            $this->usuario
        );

        $this->assertSame('300.0000', $reembolso->monto_total);
        $this->assertCount(2, $reembolso->detalles);
        $this->assertSame(2, MovimientoCaja::count());

        $this->assertSame(
            '100.0000',
            MovimientoCaja::query()
                ->where('tipo_movimiento', 'reembolso')
                ->firstOrFail()->monto
        );
    }

    public function test_rf19_no_habilita_nuevo_cobro(): void
    {
        $original = $this->original('1000');

        $this->reembolsar($original, '1000');

        $this->errorValidacion(
            fn () => $this->pendiente('1', 'transferencia'),
            'monto_total'
        );
    }

    public function test_rf20_libera_bloqueo(): void
    {
        $original = $this->original();

        $this->reembolsar($original);

        $conexion = DB::connection();

        $resultado = $conexion->selectOne(
            'SELECT IS_FREE_LOCK(?) AS libre',
            ['joysys:caja:'.sha1($conexion->getDatabaseName())],
            false
        );

        $this->assertSame(1, (int) $resultado->libre);
    }

    public function test_rf21_libera_bloqueo_tras_fallo(): void
    {
        $original = $this->original();

        $this->errorValidacion(
            fn () => $this->reembolsar($original, '101'),
            'monto'
        );

        $conexion = DB::connection();

        $resultado = $conexion->selectOne(
            'SELECT IS_FREE_LOCK(?) AS libre',
            ['joysys:caja:'.sha1($conexion->getDatabaseName())],
            false
        );

        $this->assertSame(1, (int) $resultado->libre);
    }

    public function test_rf22_reembolso_no_usa_cobro_ordinario(): void
    {
        $original = $this->original();
        $reembolso = $this->reembolsar($original);

        $this->errorValidacion(
            fn () => $this->servicio->confirmar(
                $reembolso->getKey(),
                $this->usuario
            ),
            'pago'
        );
    }

    public function test_rf23_no_modifica_venta(): void
    {
        $original = $this->original();
        $antes = $this->venta->getRawOriginal();

        $this->reembolsar($original);

        $despues = $this->venta->refresh()->getRawOriginal();

        ksort($antes);
        ksort($despues);

        $this->assertSame(
            $antes,
            $despues
        );
    }

    private function prepararReembolsoSaldoFavor(): array
    {
        $cliente = \App\Models\Cliente::create([
            'codigo' => 'RF-SF-'.\Illuminate\Support\Str::uuid(),
            'nombre' => 'Cliente de prueba',
            'activo' => true,
        ]);

        $this->venta->forceFill([
            'cliente_id' => $cliente->id,
        ])->save();

        $saldo = \App\Models\SaldoFavor::create([
            'cliente_id' => $cliente->id,
            'sucursal_id' => $this->sucursal,
            'usuario_id' => $this->usuario->id,
            'moneda' => 'MXN',
            'saldo_disponible' => '500.0000',
            'estado' => 'activo',
        ]);

        \App\Models\MovimientoSaldoFavor::create([
            'saldo_favor_id' => $saldo->id,
            'sucursal_id' => $this->sucursal,
            'usuario_id' => $this->usuario->id,
            'autorizado_por' => $this->usuario->id,
            'tipo_movimiento' => 'generacion',
            'monto' => '500.0000',
            'saldo_anterior' => '0.0000',
            'saldo_nuevo' => '500.0000',
        ]);

        $datos = $this->datos('100.0000', 'saldo_favor');

        $datos['detalles'][0]['saldo_favor_id'] = $saldo->id;

        $original = $this->servicio->registrarConfirmado(
            $datos,
            $this->usuario
        );

        $this->assertSame('confirmado', $original->estado);

        $this->assertSame(
            '400.0000',
            $saldo->refresh()->saldo_disponible
        );

        $this->assertSame(
            1,
            \App\Models\AplicacionSaldoFavor::count()
        );

        return [$saldo, $original];
    }


    private function devolverCredito(
        Pago $original,
        string $monto
    ): Pago {
        return app(
            \App\Services\Finanzas\ReembolsoService::class
        )->registrar(
            (int) $original->id,
            [
                'justificacion' => 'Restitucion de prueba',
                'detalles' => [
                    [
                        'detalle_pago_original_id' =>
                            $original->detalles()->firstOrFail()->id,
                        'monto' => $monto,
                    ],
                ],
            ],
            $this->usuario
        );
    }

    public function test_rf24_restitucion_parcial(): void
    {
        [$saldo, $original] =
            $this->prepararReembolsoSaldoFavor();

        $reembolso = $this->devolverCredito(
            $original,
            '40.0000'
        );

        $this->assertSame('confirmado', $reembolso->estado);

        $this->assertSame(
            '440.0000',
            $saldo->refresh()->saldo_disponible
        );

        $this->assertSame(
            (int) $original->id,
            (int) $reembolso->pago_original_id
        );

        $detalle = $reembolso->detalles()->firstOrFail();

        $this->assertSame('saldo_favor', $detalle->metodo_pago);

        $this->assertSame(
            (int) $saldo->id,
            (int) $detalle->saldo_favor_id
        );

        $this->assertSame(
            'aplicada',
            $saldo->aplicaciones()->firstOrFail()->estado
        );

        $this->assertSame(
            3,
            $saldo->movimientos()->count()
        );

        $this->assertSame(
            0,
            DB::table('movimientos_caja')->count()
        );
    }

    public function test_rf28_restitucion_total(): void
    {
        [$saldo, $original] =
            $this->prepararReembolsoSaldoFavor();

        $this->devolverCredito($original, '100.0000');

        $this->assertSame(
            '500.0000',
            $saldo->refresh()->saldo_disponible
        );

        $aplicacion = $saldo->aplicaciones()->firstOrFail();

        $this->assertSame('revertida', $aplicacion->estado);

        $this->assertSame(
            '100.0000',
            $aplicacion->monto_aplicado
        );

        $this->assertSame(
            (int) $this->usuario->id,
            (int) $aplicacion->revertida_por
        );

        $this->assertSame(
            'confirmado',
            $original->refresh()->estado
        );

        $this->assertSame(
            '100.0000',
            $original->monto_total
        );
    }

    public function test_rf29_restituciones_acumuladas(): void
    {
        [$saldo, $original] =
            $this->prepararReembolsoSaldoFavor();

        $this->devolverCredito($original, '25.0000');
        $this->devolverCredito($original, '35.0000');
        $this->devolverCredito($original, '40.0000');

        $this->assertSame(
            '500.0000',
            $saldo->refresh()->saldo_disponible
        );

        $this->assertSame(
            3,
            $saldo->movimientos()
                ->where('tipo_movimiento', 'restitucion')
                ->count()
        );

        $this->assertSame(
            'revertida',
            $saldo->aplicaciones()->firstOrFail()->estado
        );

        $this->assertSame(4, Pago::count());
    }

    public function test_rf30_rechaza_restitucion_excesiva(): void
    {
        [$saldo, $original] =
            $this->prepararReembolsoSaldoFavor();

        $this->devolverCredito($original, '70.0000');

        $this->errorValidacion(
            fn () => $this->devolverCredito(
                $original,
                '31.0000'
            ),
            'monto'
        );

        $this->assertSame(
            '470.0000',
            $saldo->refresh()->saldo_disponible
        );

        $this->assertSame(2, Pago::count());

        $this->assertSame(
            1,
            $saldo->movimientos()
                ->where('tipo_movimiento', 'restitucion')
                ->count()
        );
    }

    public function test_rf31_restitucion_sin_caja(): void
    {
        [$saldo, $original] =
            $this->prepararReembolsoSaldoFavor();

        $this->assertSame(
            0,
            DB::table('sesiones_caja')->count()
        );

        $this->devolverCredito($original, '50.0000');

        $this->assertSame(
            '450.0000',
            $saldo->refresh()->saldo_disponible
        );

        $this->assertSame(
            0,
            DB::table('movimientos_caja')->count()
        );
    }

    public function test_rf32_restitucion_conserva_historial(): void
    {
        [$saldo, $original] =
            $this->prepararReembolsoSaldoFavor();

        $aplicacion = $saldo->aplicaciones()->firstOrFail();

        $movimientoOriginal = $saldo->movimientos()
            ->where('tipo_movimiento', 'aplicacion')
            ->firstOrFail();

        $antes = $movimientoOriginal->getAttributes();

        $reembolso = $this->devolverCredito(
            $original,
            '30.0000'
        );

        $restitucion = $saldo->movimientos()
            ->where('tipo_movimiento', 'restitucion')
            ->firstOrFail();

        $this->assertSame(
            (int) $movimientoOriginal->id,
            (int) $restitucion->movimiento_original_id
        );

        $this->assertSame(
            (int) $aplicacion->id,
            (int) $restitucion->aplicacion_saldo_favor_id
        );

        $this->assertSame(
            (int) $reembolso->id,
            (int) $restitucion->pago_id
        );

        $this->assertSame(
            '400.0000',
            $restitucion->saldo_anterior
        );

        $this->assertSame(
            '430.0000',
            $restitucion->saldo_nuevo
        );

        $despues = $movimientoOriginal->refresh()
            ->getAttributes();

        ksort($antes);
        ksort($despues);

        $this->assertSame($antes, $despues);
    }

    public function test_rf33_restitucion_interna_idempotente(): void
    {
        [$saldo, $original] =
            $this->prepararReembolsoSaldoFavor();

        $reembolso = $this->devolverCredito(
            $original,
            '20.0000'
        );

        $detalle = $reembolso->detalles()->firstOrFail();

        $movimiento = app(
            \App\Services\Finanzas\SaldoFavorService::class
        )->restituir(
            (int) $detalle->id,
            $this->usuario
        );

        $this->assertSame(
            '420.0000',
            $saldo->refresh()->saldo_disponible
        );

        $this->assertSame(
            1,
            $saldo->movimientos()
                ->where('tipo_movimiento', 'restitucion')
                ->count()
        );

        $this->assertSame(
            (int) $reembolso->id,
            (int) $movimiento->pago_id
        );
    }

    public function test_rf34_rollback_restitucion(): void
    {
        [$saldo, $original] =
            $this->prepararReembolsoSaldoFavor();

        $dispatcher =
            \App\Models\MovimientoSaldoFavor::getEventDispatcher();

        $temporal = clone $dispatcher;

        \App\Models\MovimientoSaldoFavor::setEventDispatcher(
            $temporal
        );

        $temporal->listen(
            'eloquent.created: '.\App\Models\MovimientoSaldoFavor::class,
            function ($movimiento): void {
                if ($movimiento->tipo_movimiento === 'restitucion') {
                    throw new \RuntimeException(
                        'FALLO_RESTITUCION_RF34'
                    );
                }
            }
        );

        $detectado = false;

        try {
            $this->devolverCredito($original, '60.0000');

        } catch (\RuntimeException $e) {
            $this->assertSame(
                'FALLO_RESTITUCION_RF34',
                $e->getMessage()
            );

            $detectado = true;

        } finally {
            \App\Models\MovimientoSaldoFavor::setEventDispatcher(
                $dispatcher
            );
        }

        $this->assertTrue($detectado);

        $this->assertSame(1, Pago::count());

        $this->assertSame(
            1,
            DB::table('detalle_pagos')->count()
        );

        $this->assertSame(
            '400.0000',
            $saldo->refresh()->saldo_disponible
        );

        $this->assertSame(
            2,
            $saldo->movimientos()->count()
        );

        $this->assertSame(
            'aplicada',
            $saldo->aplicaciones()->firstOrFail()->estado
        );

        $this->devolverCredito($original, '60.0000');

        $this->assertSame(
            '460.0000',
            $saldo->refresh()->saldo_disponible
        );
    }


    public function test_rf27_check_impide_credito_sin_identificador(): void
    {
        $original = $this->pendiente(
            '100.0000',
            'transferencia'
        );

        $falloDetectado = false;

        try {
            DB::table('detalle_pagos')
                ->where('pago_id', $original->getKey())
                ->update(['metodo_pago' => 'saldo_favor']);

        } catch (\Illuminate\Database\QueryException $e) {
            $this->assertSame(
                3819,
                (int) ($e->errorInfo[1] ?? 0)
            );

            $this->assertStringContainsString(
                'chk_dp_saldo_favor',
                $e->getMessage()
            );

            $falloDetectado = true;
        }

        $this->assertTrue($falloDetectado);

        $detalle = $original->detalles()->firstOrFail();

        $this->assertSame(
            'transferencia',
            $detalle->metodo_pago
        );

        $this->assertNull($detalle->saldo_favor_id);
    }

    public function test_rf25_rollback_si_falla_partida(): void
    {
        $original = $this->original();

        $pagosAntes = Pago::count();
        $detallesAntes = DetallePago::count();
        $movimientosAntes = MovimientoCaja::count();

        $despachador = DetallePago::getEventDispatcher();
        $despachadorPrueba = clone $despachador;

        $intentos = 0;
        $pagoIntermedio = false;

        DetallePago::setEventDispatcher($despachadorPrueba);

        DetallePago::creating(function () use (
            &$intentos,
            &$pagoIntermedio,
            $original
        ): void {
            $intentos++;

            $pagoIntermedio = Pago::query()
                ->where('pago_original_id', $original->getKey())
                ->exists();

            throw new \RuntimeException(
                'FALLO_CONTROLADO_PARTIDA_REEMBOLSO'
            );
        });

        $falloDetectado = false;

        try {
            $this->reembolsar($original);
        } catch (\RuntimeException $excepcion) {
            $this->assertSame(
                'FALLO_CONTROLADO_PARTIDA_REEMBOLSO',
                $excepcion->getMessage()
            );

            $falloDetectado = true;
        } finally {
            DetallePago::setEventDispatcher($despachador);
        }

        $this->assertTrue($falloDetectado);
        $this->assertSame(1, $intentos);
        $this->assertTrue($pagoIntermedio);

        $this->assertSame($pagosAntes, Pago::count());
        $this->assertSame($detallesAntes, DetallePago::count());
        $this->assertSame($movimientosAntes, MovimientoCaja::count());

        $this->assertSame(
            0,
            Pago::query()
                ->where('pago_original_id', $original->getKey())
                ->count()
        );

        $reembolso = $this->reembolsar($original);

        $this->assertSame('confirmado', $reembolso->estado);
    }

    public function test_rf26_rollback_segundo_movimiento(): void
    {
        $this->abrirCaja();

        $datos = $this->datos('200');

        $datos['detalles'] = [
            ['metodo_pago' => 'efectivo', 'monto' => '100'],
            ['metodo_pago' => 'efectivo', 'monto' => '100'],
        ];

        $original = $this->servicio->registrarConfirmado(
            $datos,
            $this->usuario
        );

        $solicitud = [
            'justificacion' => 'Prueba de rollback atomico',
            'detalles' => [],
        ];

        foreach ($original->detalles as $detalle) {
            $solicitud['detalles'][] = [
                'detalle_pago_original_id' => $detalle->getKey(),
                'monto' => '50',
            ];
        }

        $pagosAntes = Pago::count();
        $detallesAntes = DetallePago::count();
        $movimientosAntes = MovimientoCaja::count();

        $despachador = MovimientoCaja::getEventDispatcher();
        $despachadorPrueba = clone $despachador;

        $intentos = 0;
        $primerMovimientoPersistido = false;

        MovimientoCaja::setEventDispatcher($despachadorPrueba);

        MovimientoCaja::creating(function (
            MovimientoCaja $movimiento
        ) use (
            &$intentos,
            &$primerMovimientoPersistido
        ): void {
            if ($movimiento->tipo_movimiento !== 'reembolso') {
                return;
            }

            $intentos++;

            if ($intentos === 2) {
                $primerMovimientoPersistido =
                    MovimientoCaja::query()
                        ->where('tipo_movimiento', 'reembolso')
                        ->count() === 1;

                throw new \RuntimeException(
                    'FALLO_CONTROLADO_SEGUNDO_MOVIMIENTO'
                );
            }
        });

        $falloDetectado = false;

        try {
            app(ReembolsoService::class)->registrar(
                $original->getKey(),
                $solicitud,
                $this->usuario
            );
        } catch (\RuntimeException $excepcion) {
            $this->assertSame(
                'FALLO_CONTROLADO_SEGUNDO_MOVIMIENTO',
                $excepcion->getMessage()
            );

            $falloDetectado = true;
        } finally {
            MovimientoCaja::setEventDispatcher($despachador);
        }

        $this->assertTrue($falloDetectado);
        $this->assertSame(2, $intentos);
        $this->assertTrue($primerMovimientoPersistido);

        $this->assertSame($pagosAntes, Pago::count());
        $this->assertSame($detallesAntes, DetallePago::count());
        $this->assertSame($movimientosAntes, MovimientoCaja::count());

        $this->assertSame(
            0,
            MovimientoCaja::query()
                ->where('tipo_movimiento', 'reembolso')
                ->count()
        );

        $reembolso = app(ReembolsoService::class)->registrar(
            $original->getKey(),
            $solicitud,
            $this->usuario
        );

        $this->assertSame('confirmado', $reembolso->estado);
        $this->assertSame('100.0000', $reembolso->monto_total);

        $this->assertSame(
            $movimientosAntes + 2,
            MovimientoCaja::count()
        );
    }


    private function crearCreditoIntegrado(
        string $monto,
        ?\App\Models\Cliente $cliente = null
    ): \App\Models\SaldoFavor {
        $cliente ??= \App\Models\Cliente::create([
            'codigo' => 'RFI-'.\Illuminate\Support\Str::uuid(),
            'nombre' => 'Cliente integracion reembolsos',
            'activo' => true,
        ]);

        $this->venta->forceFill([
            'cliente_id' => $cliente->id,
        ])->save();

        $saldo = \App\Models\SaldoFavor::create([
            'cliente_id' => $cliente->id,
            'sucursal_id' => $this->sucursal,
            'usuario_id' => $this->usuario->id,
            'moneda' => 'MXN',
            'saldo_disponible' => $monto,
            'estado' => 'activo',
        ]);

        \App\Models\MovimientoSaldoFavor::create([
            'saldo_favor_id' => $saldo->id,
            'sucursal_id' => $this->sucursal,
            'usuario_id' => $this->usuario->id,
            'autorizado_por' => $this->usuario->id,
            'tipo_movimiento' => 'generacion',
            'monto' => $monto,
            'saldo_anterior' => '0.0000',
            'saldo_nuevo' => $monto,
        ]);

        return $saldo;
    }

    private function cobrarIntegrado(array $detalles): Pago
    {
        $total = '0.0000';

        foreach ($detalles as $detalle) {
            $total = bcadd(
                $total,
                $detalle['monto'],
                4
            );
        }

        $datos = $this->datos($total);

        $datos['detalles'] = $detalles;

        return $this->servicio->registrarConfirmado(
            $datos,
            $this->usuario
        );
    }

    private function reembolsarIntegrado(
        Pago $original,
        ?\App\Models\User $usuario = null
    ): Pago {
        $detalles = $original->detalles()
            ->orderBy('id')
            ->get()
            ->map(fn ($detalle) => [
                'detalle_pago_original_id' => $detalle->id,
                'monto' => $detalle->monto,
            ])
            ->all();

        return app(
            \App\Services\Finanzas\ReembolsoService::class
        )->registrar(
            (int) $original->id,
            [
                'justificacion' => 'Reembolso integrado de prueba',
                'detalles' => $detalles,
            ],
            $usuario ?? $this->usuario
        );
    }

    public function test_rf35_reactiva_credito_agotado(): void
    {
        $saldo = $this->crearCreditoIntegrado('100.0000');

        $original = $this->cobrarIntegrado([
            [
                'metodo_pago' => 'saldo_favor',
                'monto' => '100.0000',
                'saldo_favor_id' => $saldo->id,
            ],
        ]);

        $this->assertSame(
            'agotado',
            $saldo->refresh()->estado
        );

        $this->assertSame(
            '0.0000',
            $saldo->saldo_disponible
        );

        $this->devolverCredito($original, '35.0000');

        $this->assertSame(
            'activo',
            $saldo->refresh()->estado
        );

        $this->assertSame(
            '35.0000',
            $saldo->saldo_disponible
        );

        $this->assertSame(
            'aplicada',
            $saldo->aplicaciones()->firstOrFail()->estado
        );
    }

    public function test_rf36_reembolso_efectivo_y_credito(): void
    {
        $this->abrirCaja();

        $saldo = $this->crearCreditoIntegrado('500.0000');

        $original = $this->cobrarIntegrado([
            [
                'metodo_pago' => 'efectivo',
                'monto' => '60.0000',
            ],
            [
                'metodo_pago' => 'saldo_favor',
                'monto' => '40.0000',
                'saldo_favor_id' => $saldo->id,
            ],
        ]);

        $this->assertSame(
            '460.0000',
            $saldo->refresh()->saldo_disponible
        );

        $reembolso = $this->reembolsarIntegrado($original);

        $this->assertSame(
            '100.0000',
            $reembolso->monto_total
        );

        $this->assertSame(
            '500.0000',
            $saldo->refresh()->saldo_disponible
        );

        $this->assertSame(
            'revertida',
            $saldo->aplicaciones()->firstOrFail()->estado
        );

        $this->assertSame(
            2,
            DB::table('movimientos_caja')->count()
        );

        $movimiento = \App\Models\MovimientoCaja::query()
            ->where('tipo_movimiento', 'reembolso')
            ->sole();

        $this->assertSame('60.0000', $movimiento->monto);

        $detalle = $reembolso->detalles()
            ->where('metodo_pago', 'saldo_favor')
            ->sole();

        $this->assertSame(
            (int) $saldo->id,
            (int) $detalle->saldo_favor_id
        );

        $this->assertNull(
            $detalle->movimientoCaja()->first()
        );
    }

    public function test_rf37_reembolso_varios_creditos(): void
    {
        $primero = $this->crearCreditoIntegrado('300.0000');

        $segundo = $this->crearCreditoIntegrado(
            '200.0000',
            $primero->cliente
        );

        $original = $this->cobrarIntegrado([
            [
                'metodo_pago' => 'saldo_favor',
                'monto' => '80.0000',
                'saldo_favor_id' => $primero->id,
            ],
            [
                'metodo_pago' => 'saldo_favor',
                'monto' => '120.0000',
                'saldo_favor_id' => $segundo->id,
            ],
        ]);

        $this->assertSame(
            '220.0000',
            $primero->refresh()->saldo_disponible
        );

        $this->assertSame(
            '80.0000',
            $segundo->refresh()->saldo_disponible
        );

        $reembolso = $this->reembolsarIntegrado($original);

        $this->assertSame(
            '200.0000',
            $reembolso->monto_total
        );

        $this->assertSame(
            '300.0000',
            $primero->refresh()->saldo_disponible
        );

        $this->assertSame(
            '200.0000',
            $segundo->refresh()->saldo_disponible
        );

        $this->assertSame(
            2,
            \App\Models\AplicacionSaldoFavor::query()
                ->where('estado', 'revertida')
                ->count()
        );

        $this->assertSame(
            2,
            \App\Models\MovimientoSaldoFavor::query()
                ->where('tipo_movimiento', 'restitucion')
                ->count()
        );

        $this->assertSame(
            0,
            DB::table('movimientos_caja')->count()
        );
    }

    public function test_rf38_mixto_sin_caja_abierta(): void
    {
        $this->abrirCaja();

        $saldo = $this->crearCreditoIntegrado('500.0000');

        $original = $this->cobrarIntegrado([
            [
                'metodo_pago' => 'efectivo',
                'monto' => '60.0000',
            ],
            [
                'metodo_pago' => 'saldo_favor',
                'monto' => '40.0000',
                'saldo_favor_id' => $saldo->id,
            ],
        ]);

        app(
            \App\Services\Caja\SesionCajaService::class
        )->cerrar(
            ['efectivo_contado' => '560.0000'],
            $this->usuario
        );

        $detectado = false;

        try {
            $this->reembolsarIntegrado($original);

        } catch (\Illuminate\Validation\ValidationException $e) {
            $detectado = true;
        }

        $this->assertTrue($detectado);

        $this->assertSame(
            '460.0000',
            $saldo->refresh()->saldo_disponible
        );

        $this->assertSame(1, Pago::count());

        $this->assertSame(
            1,
            DB::table('movimientos_caja')->count()
        );

        $this->assertSame(
            0,
            $saldo->movimientos()
                ->where('tipo_movimiento', 'restitucion')
                ->count()
        );
    }

    public function test_rf39_rollback_efectivo_y_restitucion(): void
    {
        $this->abrirCaja();

        $saldo = $this->crearCreditoIntegrado('500.0000');

        $original = $this->cobrarIntegrado([
            [
                'metodo_pago' => 'saldo_favor',
                'monto' => '40.0000',
                'saldo_favor_id' => $saldo->id,
            ],
            [
                'metodo_pago' => 'efectivo',
                'monto' => '60.0000',
            ],
        ]);

        $dispatcher =
            \App\Models\MovimientoCaja::getEventDispatcher();

        $temporal = clone $dispatcher;

        \App\Models\MovimientoCaja::setEventDispatcher(
            $temporal
        );

        $temporal->listen(
            'eloquent.created: '.\App\Models\MovimientoCaja::class,
            function ($movimiento): void {
                if ($movimiento->tipo_movimiento === 'reembolso') {
                    throw new \RuntimeException(
                        'FALLO_MIXTO_RF39'
                    );
                }
            }
        );

        $detectado = false;

        try {
            $this->reembolsarIntegrado($original);

        } catch (\RuntimeException $e) {
            $this->assertSame(
                'FALLO_MIXTO_RF39',
                $e->getMessage()
            );

            $detectado = true;

        } finally {
            \App\Models\MovimientoCaja::setEventDispatcher(
                $dispatcher
            );
        }

        $this->assertTrue($detectado);

        $this->assertSame(1, Pago::count());

        $this->assertSame(
            2,
            DB::table('detalle_pagos')->count()
        );

        $this->assertSame(
            1,
            DB::table('movimientos_caja')->count()
        );

        $this->assertSame(
            '460.0000',
            $saldo->refresh()->saldo_disponible
        );

        $this->assertSame(
            'aplicada',
            $saldo->aplicaciones()->firstOrFail()->estado
        );

        $this->assertSame(
            2,
            $saldo->movimientos()->count()
        );

        $this->reembolsarIntegrado($original);

        $this->assertSame(
            '500.0000',
            $saldo->refresh()->saldo_disponible
        );

        $this->assertSame(
            2,
            DB::table('movimientos_caja')->count()
        );
    }

    public function test_rf40_rollback_segunda_restitucion(): void
    {
        $primero = $this->crearCreditoIntegrado('300.0000');

        $segundo = $this->crearCreditoIntegrado(
            '200.0000',
            $primero->cliente
        );

        $original = $this->cobrarIntegrado([
            [
                'metodo_pago' => 'saldo_favor',
                'monto' => '80.0000',
                'saldo_favor_id' => $primero->id,
            ],
            [
                'metodo_pago' => 'saldo_favor',
                'monto' => '120.0000',
                'saldo_favor_id' => $segundo->id,
            ],
        ]);

        $dispatcher =
            \App\Models\MovimientoSaldoFavor::getEventDispatcher();

        $temporal = clone $dispatcher;

        \App\Models\MovimientoSaldoFavor::setEventDispatcher(
            $temporal
        );

        $cantidad = 0;

        $temporal->listen(
            'eloquent.created: '.\App\Models\MovimientoSaldoFavor::class,
            function ($movimiento) use (&$cantidad): void {
                if ($movimiento->tipo_movimiento === 'restitucion') {
                    $cantidad++;

                    if ($cantidad === 2) {
                        throw new \RuntimeException(
                            'FALLO_SEGUNDA_RF40'
                        );
                    }
                }
            }
        );

        $detectado = false;

        try {
            $this->reembolsarIntegrado($original);

        } catch (\RuntimeException $e) {
            $this->assertSame(
                'FALLO_SEGUNDA_RF40',
                $e->getMessage()
            );

            $detectado = true;

        } finally {
            \App\Models\MovimientoSaldoFavor::setEventDispatcher(
                $dispatcher
            );
        }

        $this->assertTrue($detectado);
        $this->assertSame(2, $cantidad);
        $this->assertSame(1, Pago::count());

        $this->assertSame(
            '220.0000',
            $primero->refresh()->saldo_disponible
        );

        $this->assertSame(
            '80.0000',
            $segundo->refresh()->saldo_disponible
        );

        $this->assertSame(
            0,
            \App\Models\MovimientoSaldoFavor::query()
                ->where('tipo_movimiento', 'restitucion')
                ->count()
        );

        $this->assertSame(
            2,
            \App\Models\AplicacionSaldoFavor::query()
                ->where('estado', 'aplicada')
                ->count()
        );

        $this->reembolsarIntegrado($original);

        $this->assertSame(
            '300.0000',
            $primero->refresh()->saldo_disponible
        );

        $this->assertSame(
            '200.0000',
            $segundo->refresh()->saldo_disponible
        );
    }

    public function test_rf41_otro_usuario_autorizado(): void
    {
        [$saldo, $original] =
            $this->prepararReembolsoSaldoFavor();

        $autorizador = $this->crearUsuario(
            $this->sucursal,
            ['pagos.ver', 'pagos.reembolsar']
        );

        $reembolso = $this->reembolsarIntegrado(
            $original,
            $autorizador
        );

        $this->assertSame(
            (int) $autorizador->id,
            (int) $reembolso->recibido_por
        );

        $this->assertSame(
            '500.0000',
            $saldo->refresh()->saldo_disponible
        );

        $movimiento = $saldo->movimientos()
            ->where('tipo_movimiento', 'restitucion')
            ->sole();

        $this->assertSame(
            (int) $autorizador->id,
            (int) $movimiento->usuario_id
        );

        $this->assertSame(
            (int) $autorizador->id,
            (int) $movimiento->autorizado_por
        );

        $this->assertSame(
            (int) $autorizador->id,
            (int) $saldo->aplicaciones()
                ->firstOrFail()->revertida_por
        );

        $this->assertSame(
            (int) $this->usuario->id,
            (int) $original->refresh()->recibido_por
        );
    }

    public function test_rf42_rechaza_partidas_duplicadas(): void
    {
        [$saldo, $original] =
            $this->prepararReembolsoSaldoFavor();

        $detalle = $original->detalles()->firstOrFail();

        $detectado = false;

        try {
            app(
                \App\Services\Finanzas\ReembolsoService::class
            )->registrar(
                (int) $original->id,
                [
                    'justificacion' => 'Partidas duplicadas',
                    'detalles' => [
                        [
                            'detalle_pago_original_id' => $detalle->id,
                            'monto' => '20.0000',
                        ],
                        [
                            'detalle_pago_original_id' => $detalle->id,
                            'monto' => '30.0000',
                        ],
                    ],
                ],
                $this->usuario
            );

        } catch (\Illuminate\Validation\ValidationException $e) {
            $errores = $e->errors();

            $this->assertArrayHasKey(
                'detalles.1.detalle_pago_original_id',
                $errores
            );

            $this->assertContains(
                'La partida original esta duplicada.',
                $errores['detalles.1.detalle_pago_original_id']
            );

            $detectado = true;
        }

        $this->assertTrue($detectado);

        $this->assertSame(1, Pago::count());

        $this->assertSame(
            1,
            DB::table('detalle_pagos')->count()
        );

        $this->assertSame(
            '400.0000',
            $saldo->refresh()->saldo_disponible
        );

        $this->assertSame(
            2,
            $saldo->movimientos()->count()
        );

        $this->assertSame(
            0,
            $saldo->movimientos()
                ->where('tipo_movimiento', 'restitucion')
                ->count()
        );

        $this->assertSame(
            'aplicada',
            $saldo->aplicaciones()->firstOrFail()->estado
        );

        $this->assertSame(
            0,
            DB::table('movimientos_caja')->count()
        );

        $this->assertSame(
            'confirmado',
            $original->refresh()->estado
        );

        $this->assertSame(
            '100.0000',
            $original->monto_total
        );
    }



    public function test_rf43_reembolso_idempotente(): void
    {
        $original = app(\App\Services\Finanzas\PagoService::class)
            ->registrarConfirmado(
                $this->datos('100.0000', 'transferencia'),
                $this->usuario
            );

        $datos = [
            'justificacion' => 'Reembolso autorizado',
            'detalles' => [[
                'detalle_pago_original_id' => $original->detalles->first()->id,
                'monto' => '40.0000',
            ]],
        ];

        $servicio = app(\App\Services\Finanzas\ReembolsoService::class);

        $primero = $servicio->registrar(
            $original->id, $datos, $this->usuario, 'reembolso-01'
        );

        $segundo = $servicio->registrar(
            $original->id, $datos, $this->usuario, 'reembolso-01'
        );

        $this->assertSame($primero->id, $segundo->id);
        $this->assertSame(2, \App\Models\Pago::count());
        $this->assertSame(2, \App\Models\DetallePago::count());
        $this->assertSame(1, \App\Models\OperacionIdempotente::count());
    }

    public function test_rf44_reembolso_efectivo_idempotente(): void
    {
        $this->abrirCaja();

        $original = app(\App\Services\Finanzas\PagoService::class)
            ->registrarConfirmado(
                $this->datos('100.0000', 'efectivo'),
                $this->usuario
            );

        $datos = [
            'justificacion' => 'Reembolso en efectivo',
            'detalles' => [[
                'detalle_pago_original_id' => $original->detalles->first()->id,
                'monto' => '40.0000',
            ]],
        ];

        $servicio = app(\App\Services\Finanzas\ReembolsoService::class);

        $primero = $servicio->registrar(
            $original->id, $datos, $this->usuario, 'reembolso-efectivo-01'
        );

        $segundo = $servicio->registrar(
            $original->id, $datos, $this->usuario, 'reembolso-efectivo-01'
        );

        $this->assertSame($primero->id, $segundo->id);

        $this->assertSame(
            1,
            \App\Models\MovimientoCaja::where(
                'tipo_movimiento', 'reembolso'
            )->count()
        );

        $this->assertSame(2, \App\Models\Pago::count());
    }

    public function test_rf45_rechaza_clave_reembolso_reutilizada(): void
    {
        $original = app(\App\Services\Finanzas\PagoService::class)
            ->registrarConfirmado(
                $this->datos('100.0000', 'transferencia'),
                $this->usuario
            );

        $datos = [
            'justificacion' => 'Reembolso autorizado',
            'detalles' => [[
                'detalle_pago_original_id' => $original->detalles->first()->id,
                'monto' => '20.0000',
            ]],
        ];

        $servicio = app(\App\Services\Finanzas\ReembolsoService::class);

        $servicio->registrar(
            $original->id, $datos, $this->usuario, 'conflicto-rf-01'
        );

        $datos['detalles'][0]['monto'] = '30.0000';

        try {
            $servicio->registrar(
                $original->id, $datos, $this->usuario, 'conflicto-rf-01'
            );

            $this->fail('Se permitio reutilizar una clave.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }

        $this->assertSame(2, \App\Models\Pago::count());
        $this->assertSame(1, \App\Models\OperacionIdempotente::count());
    }

    public function test_rf46_rollback_idempotencia_reembolso(): void
    {
        $original = app(\App\Services\Finanzas\PagoService::class)
            ->registrarConfirmado(
                $this->datos('100.0000', 'transferencia'),
                $this->usuario
            );

        $datos = [
            'justificacion' => 'Reembolso autorizado',
            'detalles' => [[
                'detalle_pago_original_id' => $original->detalles->first()->id,
                'monto' => '101.0000',
            ]],
        ];

        $servicio = app(\App\Services\Finanzas\ReembolsoService::class);

        try {
            $servicio->registrar(
                $original->id, $datos, $this->usuario, 'rollback-rf-01'
            );

            $this->fail('Se permitio un reembolso excesivo.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }

        $this->assertSame(1, \App\Models\Pago::count());
        $this->assertSame(0, \App\Models\OperacionIdempotente::count());

        $datos['detalles'][0]['monto'] = '40.0000';

        $reembolso = $servicio->registrar(
            $original->id, $datos, $this->usuario, 'rollback-rf-01'
        );

        $this->assertSame('confirmado', $reembolso->estado);
        $this->assertSame(2, \App\Models\Pago::count());
        $this->assertSame(1, \App\Models\OperacionIdempotente::count());
    }



    public function test_rf47_restitucion_credito_idempotente(): void
    {
        [$saldo, $original] = $this->prepararReembolsoSaldoFavor();

        $datos = [
            'justificacion' => 'Restitucion financiera de prueba',
            'detalles' => [
                [
                    'detalle_pago_original_id' =>
                        $original->detalles()->firstOrFail()->id,
                    'monto' => '40.0000',
                ],
            ],
        ];

        $servicio = app(
            \App\Services\Finanzas\ReembolsoService::class
        );

        $primero = $servicio->registrar(
            $original->id,
            $datos,
            $this->usuario,
            'rf47-restitucion'
        );

        $segundo = $servicio->registrar(
            $original->id,
            $datos,
            $this->usuario,
            'rf47-restitucion'
        );

        $this->assertSame($primero->id, $segundo->id);

        $this->assertSame(
            '440.0000',
            $saldo->refresh()->saldo_disponible
        );

        $this->assertSame(
            1,
            \App\Models\Pago::query()
                ->where('pago_original_id', $original->id)
                ->count()
        );

        $this->assertSame(
            1,
            \App\Models\MovimientoSaldoFavor::query()
                ->where('saldo_favor_id', $saldo->id)
                ->where('tipo_movimiento', 'restitucion')
                ->count()
        );

        $this->assertSame(
            1,
            \App\Models\OperacionIdempotente::query()
                ->where('clave', 'rf47-restitucion')
                ->count()
        );
    }

    public function test_rf48_reembolso_repetido_revalida_permiso(): void
    {
        [$saldo, $original] = $this->prepararReembolsoSaldoFavor();

        $datos = [
            'justificacion' => 'Reembolso autorizado de prueba',
            'detalles' => [
                [
                    'detalle_pago_original_id' =>
                        $original->detalles()->firstOrFail()->id,
                    'monto' => '40.0000',
                ],
            ],
        ];

        $servicio = app(
            \App\Services\Finanzas\ReembolsoService::class
        );

        $primero = $servicio->registrar(
            $original->id,
            $datos,
            $this->usuario,
            'rf48-revocacion'
        );

        $permisoId = \Illuminate\Support\Facades\DB::table('permisos')
            ->where('clave', 'pagos.reembolsar')
            ->value('id');

        $eliminados = \Illuminate\Support\Facades\DB::table('rol_permiso')
            ->where('rol_id', $this->usuario->role_id)
            ->where('permiso_id', $permisoId)
            ->delete();

        $this->assertSame(1, $eliminados);

        try {
            $servicio->registrar(
                $original->id,
                $datos,
                $this->usuario,
                'rf48-revocacion'
            );

            $this->fail('Se admitio un permiso revocado.');
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->assertNotNull($e);
        }

        $this->assertSame(
            '440.0000',
            $saldo->refresh()->saldo_disponible
        );

        $this->assertSame(
            'confirmado',
            $primero->refresh()->estado
        );

        $this->assertSame(
            1,
            \App\Models\Pago::query()
                ->where('pago_original_id', $original->id)
                ->count()
        );
    }

}
