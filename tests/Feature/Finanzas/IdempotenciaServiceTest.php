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

final class IdempotenciaServiceTest extends TestCase
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


    private function idempotencia(): \App\Services\Finanzas\IdempotenciaService
    {
        return app(
            \App\Services\Finanzas\IdempotenciaService::class
        );
    }

    private function ejecutarPrueba(
        string $clave,
        array $datos,
        \Closure $operacion
    ): \App\Models\OperacionIdempotente {
        return $this->idempotencia()->ejecutar(
            $this->usuario,
            'pagos.registrar',
            'prueba_financiera',
            $clave,
            $datos,
            'venta',
            $operacion
        );
    }

    public function test_id01_registro_persistente(): void
    {
        $registro = $this->ejecutarPrueba(
            'operacion-01',
            ['monto' => '100.0000'],
            fn () => (int) $this->venta->id
        );

        $this->assertSame('venta', $registro->resultado_tipo);
        $this->assertEquals(
            $this->venta->id,
            $registro->resultado_id
        );

        $this->assertDatabaseHas('operaciones_idempotentes', [
            'id' => $registro->id,
            'clave' => 'operacion-01',
            'usuario_id' => $this->usuario->id,
            'sucursal_id' => $this->sucursal,
        ]);
    }

    public function test_id02_repeticion_no_ejecuta(): void
    {
        $veces = 0;

        $operacion = function () use (&$veces): int {
            $veces++;

            return (int) $this->venta->id;
        };

        $a = $this->ejecutarPrueba(
            'repeticion', ['monto' => '100'], $operacion
        );

        $b = $this->ejecutarPrueba(
            'repeticion', ['monto' => '100'], $operacion
        );

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, $veces);
        $this->assertDatabaseCount('operaciones_idempotentes', 1);
    }

    public function test_id03_rechaza_clave_reutilizada(): void
    {
        $this->ejecutarPrueba(
            'conflicto',
            ['monto' => '100'],
            fn () => (int) $this->venta->id
        );

        $this->expectException(
            \Illuminate\Validation\ValidationException::class
        );

        $this->ejecutarPrueba(
            'conflicto',
            ['monto' => '200'],
            fn () => (int) $this->venta->id
        );
    }

    public function test_id04_orden_de_campos(): void
    {
        $a = $this->ejecutarPrueba(
            'orden',
            ['monto' => '100', 'moneda' => 'MXN'],
            fn () => (int) $this->venta->id
        );

        $b = $this->ejecutarPrueba(
            'orden',
            ['moneda' => 'MXN', 'monto' => '100'],
            fn () => throw new \LogicException('No debe ejecutarse.')
        );

        $this->assertSame($a->id, $b->id);
    }

    public function test_id05_rollback_y_reintento(): void
    {
        $antes = \Illuminate\Support\Facades\DB::table(
            'operaciones_idempotentes'
        )->count();

        try {
            $this->ejecutarPrueba(
                'rollback',
                ['monto' => '100'],
                function (): int {
                    throw new \RuntimeException('Fallo controlado.');
                }
            );

            $this->fail('La operacion debio fallar.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Fallo controlado.', $e->getMessage());
        }

        $this->assertDatabaseCount(
            'operaciones_idempotentes',
            $antes
        );

        $r = $this->ejecutarPrueba(
            'rollback',
            ['monto' => '100'],
            fn () => (int) $this->venta->id
        );

        $this->assertNotNull($r->resultado_id);
    }

    public function test_id06_resultado_invalido_revierte(): void
    {
        try {
            $this->ejecutarPrueba(
                'resultado-invalido',
                [],
                fn () => 0
            );

            $this->fail('Resultado invalido aceptado.');
        } catch (\LogicException $e) {
            $this->assertStringContainsString(
                'identificador valido',
                $e->getMessage()
            );
        }

        $this->assertDatabaseCount('operaciones_idempotentes', 0);
    }

    public function test_id07_claves_distinguen_mayusculas(): void
    {
        $a = $this->ejecutarPrueba(
            'Clave-A', [], fn () => (int) $this->venta->id
        );

        $b = $this->ejecutarPrueba(
            'clave-a', [], fn () => (int) $this->venta->id
        );

        $this->assertNotSame($a->id, $b->id);
    }

    public function test_id08_rechaza_clave_vacia(): void
    {
        $this->expectException(
            \Illuminate\Validation\ValidationException::class
        );

        $this->ejecutarPrueba(
            '', [], fn () => (int) $this->venta->id
        );
    }

    public function test_id09_rechaza_clave_larga(): void
    {
        $this->expectException(
            \Illuminate\Validation\ValidationException::class
        );

        $this->ejecutarPrueba(
            str_repeat('a', 101),
            [],
            fn () => (int) $this->venta->id
        );
    }

    public function test_id10_rechaza_float(): void
    {
        $this->expectException(
            \Illuminate\Validation\ValidationException::class
        );

        $this->ejecutarPrueba(
            'float',
            ['monto' => 100.25],
            fn () => (int) $this->venta->id
        );
    }

    public function test_id11_preserva_orden_de_listas(): void
    {
        $a = $this->idempotencia()->hashSolicitud([
            'detalles' => ['100', '200'],
        ]);

        $b = $this->idempotencia()->hashSolicitud([
            'detalles' => ['200', '100'],
        ]);

        $this->assertNotSame($a, $b);
    }

    public function test_id12_rechaza_usuario_sin_permiso(): void
    {
        $usuario = $this->crearUsuario(
            $this->sucursal,
            ['pagos.ver']
        );

        $this->expectException(
            \Illuminate\Auth\Access\AuthorizationException::class
        );

        $this->idempotencia()->ejecutar(
            $usuario,
            'pagos.registrar',
            'prueba_financiera',
            'sin-permiso',
            [],
            'venta',
            fn () => throw new \LogicException('No debe ejecutarse.')
        );
    }

    public function test_id13_aislamiento_por_usuario(): void
    {
        $otro = $this->crearUsuario($this->sucursal);

        $otraVenta = $this->crearVenta(
            $otro,
            $this->sucursal
        );

        $a = $this->ejecutarPrueba(
            'compartida',
            [],
            fn () => (int) $this->venta->id
        );

        $b = $this->idempotencia()->ejecutar(
            $otro,
            'pagos.registrar',
            'prueba_financiera',
            'compartida',
            [],
            'venta',
            fn () => (int) $otraVenta->id
        );

        $this->assertNotSame($a->id, $b->id);
        $this->assertEquals($otro->id, $b->usuario_id);
    }

    public function test_id14_aislamiento_por_operacion(): void
    {
        $a = $this->ejecutarPrueba(
            'misma-clave',
            [],
            fn () => (int) $this->venta->id
        );

        $b = $this->idempotencia()->ejecutar(
            $this->usuario,
            'pagos.registrar',
            'otra_operacion',
            'misma-clave',
            [],
            'venta',
            fn () => (int) $this->venta->id
        );

        $this->assertNotSame($a->id, $b->id);
    }

    public function test_id15_rollback_efecto_financiero(): void
    {
        $antes = \App\Models\Pago::query()->count();

        try {
            $this->ejecutarPrueba(
                'rollback-financiero',
                ['monto' => '100'],
                function (): int {
                    $this->pendiente('100', 'transferencia');

                    throw new \RuntimeException(
                        'Fallo posterior al pago.'
                    );
                }
            );

            $this->fail('Se esperaba rollback.');
        } catch (\RuntimeException $e) {
            $this->assertSame(
                'Fallo posterior al pago.',
                $e->getMessage()
            );
        }

        $this->assertSame(
            $antes,
            \App\Models\Pago::query()->count()
        );

        $this->assertDatabaseCount(
            'operaciones_idempotentes',
            0
        );
    }
}