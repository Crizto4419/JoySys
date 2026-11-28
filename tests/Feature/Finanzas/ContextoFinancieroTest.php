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

final class ContextoFinancieroTest extends TestCase
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


    private function contextoCaja(): \App\Services\Caja\CajaService
    {
        return app(\App\Services\Caja\CajaService::class);
    }

    public function test_ct01_sin_contexto(): void
    {
        $this->assertFalse(
            $this->contextoCaja()->tieneBloqueoTransaccional()
        );
    }

    public function test_ct02_contexto_activo(): void
    {
        $caja = $this->contextoCaja();

        $caja->ejecutar(
            $this->usuario,
            $this->sucursal,
            function () use ($caja) {
                $this->assertTrue(
                    $caja->tieneBloqueoTransaccional()
                );
            },
            false,
            'pagos.registrar'
        );

        $this->assertFalse($caja->tieneBloqueoTransaccional());
    }

    public function test_ct03_contexto_entre_instancias(): void
    {
        $this->contextoCaja()->ejecutar(
            $this->usuario,
            $this->sucursal,
            function () {
                $this->assertTrue(
                    $this->contextoCaja()->tieneBloqueoTransaccional()
                );
            },
            false,
            'pagos.registrar'
        );
    }

    public function test_ct04_anidamiento_mismo_actor(): void
    {
        $caja = $this->contextoCaja();

        $resultado = $caja->ejecutar(
            $this->usuario,
            $this->sucursal,
            function () use ($caja) {
                $valor = $this->contextoCaja()->ejecutar(
                    $this->usuario,
                    $this->sucursal,
                    fn () => 123,
                    false,
                    'pagos.registrar'
                );

                $this->assertTrue(
                    $caja->tieneBloqueoTransaccional()
                );

                return $valor;
            },
            false,
            'pagos.registrar'
        );

        $this->assertSame(123, $resultado);
        $this->assertFalse($caja->tieneBloqueoTransaccional());
    }

    public function test_ct05_rechaza_otro_actor(): void
    {
        $otro = $this->crearUsuario($this->sucursal);

        $this->expectException(
            \Illuminate\Auth\Access\AuthorizationException::class
        );

        $this->contextoCaja()->ejecutar(
            $this->usuario,
            $this->sucursal,
            function () use ($otro) {
                $this->contextoCaja()->ejecutar(
                    $otro,
                    $this->sucursal,
                    fn () => null,
                    false,
                    'pagos.registrar'
                );
            },
            false,
            'pagos.registrar'
        );
    }

    public function test_ct06_rechaza_otra_sucursal(): void
    {
        $otra = $this->crearSucursal();

        $this->expectException(
            \Illuminate\Auth\Access\AuthorizationException::class
        );

        $this->contextoCaja()->ejecutar(
            $this->usuario,
            $this->sucursal,
            function () use ($otra) {
                $this->contextoCaja()->ejecutar(
                    $this->usuario,
                    $otra,
                    fn () => null,
                    false,
                    'pagos.registrar'
                );
            },
            false,
            'pagos.registrar'
        );
    }

    public function test_ct07_limpia_contexto_tras_fallo(): void
    {
        $caja = $this->contextoCaja();

        try {
            $caja->ejecutar(
                $this->usuario,
                $this->sucursal,
                fn () => throw new \RuntimeException('fallo controlado'),
                false,
                'pagos.registrar'
            );

            $this->fail('Se esperaba una excepcion.');
        } catch (\RuntimeException $e) {
            $this->assertSame('fallo controlado', $e->getMessage());
        }

        $this->assertFalse($caja->tieneBloqueoTransaccional());

        $resultado = $caja->ejecutar(
            $this->usuario,
            $this->sucursal,
            fn () => true,
            false,
            'pagos.registrar'
        );

        $this->assertTrue($resultado);
    }

    public function test_ct08_bloqueo_manual_no_es_contexto(): void
    {
        $conexion = \Illuminate\Support\Facades\DB::connection();

        $nombre = 'joysys:caja:'.sha1(
            $conexion->getDatabaseName()
        );

        $resultado = $conexion->selectOne(
            'SELECT GET_LOCK(?, 10) AS adquirido',
            [$nombre],
            false
        );

        $this->assertSame(1, (int) $resultado->adquirido);

        try {
            $this->assertFalse(
                $this->contextoCaja()->tieneBloqueoTransaccional()
            );
        } finally {
            $conexion->selectOne(
                'SELECT RELEASE_LOCK(?) AS liberado',
                [$nombre],
                false
            );
        }
    }

    public function test_ct09_permiso_anidado_independiente(): void
    {
        $usuario = $this->crearUsuario(
            $this->sucursal,
            ['pagos.registrar']
        );

        $this->expectException(
            \Illuminate\Auth\Access\AuthorizationException::class
        );

        $this->contextoCaja()->ejecutar(
            $usuario,
            $this->sucursal,
            function () use ($usuario) {
                $this->contextoCaja()->ejecutar(
                    $usuario,
                    $this->sucursal,
                    fn () => null,
                    false,
                    'pagos.reembolsar'
                );
            },
            false,
            'pagos.registrar'
        );
    }
}
