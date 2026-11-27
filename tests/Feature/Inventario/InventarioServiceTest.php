<?php

namespace Tests\Feature\Inventario;

use App\Models\Categoria;
use App\Models\Metal;
use App\Models\Pureza;
use App\Models\Producto;
use App\Models\Pieza;
use App\Models\Existencia;
use App\Models\MovimientoInventario;
use App\Models\User;
use App\Services\Inventario\InventarioService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

class InventarioServiceTest extends TestCase
{
    private User $admin;
    private Categoria $categoria;
    private Metal $metal;
    private Pureza $pureza;
    private InventarioService $inventario;
    private int $sucursalId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame(
            'joysys_testing',
            DB::connection()->getDatabaseName()
        );

        $rolId = DB::table('roles')->insertGetId([
            'nombre' => 'Administrador',
            'slug' => 'administrador',
            'estado' => 'activo',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $permisos = [
            'inventario.ver',
            'inventario.registrar_entrada',
            'inventario.registrar_salida',
            'inventario.ajustar',
            'incidencias.ver',
            'incidencias.reportar',
            'incidencias.resolver',
        ];

        foreach ($permisos as $clave) {
            $permisoId = DB::table('permisos')->insertGetId([
                'clave' => $clave,
                'nombre' => $clave,
                'modulo' => explode('.', $clave)[0],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('rol_permiso')->insert([
                'rol_id' => $rolId,
                'permiso_id' => $permisoId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->admin = User::factory()->create([
            'role_id' => $rolId,
            'estado' => 'activo',
        ]);

        $this->sucursalId = DB::table('sucursales')->insertGetId([
            'nombre' => 'Sucursal de pruebas',
            'codigo' => 'SUC-INV-TEST',
            'estado' => 'activa',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('empleado_sucursal')->insert([
            'usuario_id' => $this->admin->id,
            'sucursal_id' => $this->sucursalId,
            'es_principal' => true,
            'fecha_inicio' => now('America/Mexico_City')
                ->subDay()->toDateString(),
            'fecha_fin' => null,
            'estado' => 'activo',
            'asignado_por' => $this->admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $estados = [
            'disponible' => 'Disponible',
            'apartado' => 'Apartado',
            'vendido' => 'Vendido',
            'reparacion' => 'Reparacion',
            'danado' => 'Danado',
            'perdido' => 'Perdido',
        ];

        foreach ($estados as $codigo => $nombre) {
            DB::table('estados_inventario')->insert([
                'codigo' => $codigo,
                'nombre' => $nombre,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->categoria = Categoria::create([
            'nombre' => 'Joyeria de prueba',
            'estado' => 'activa',
        ]);

        $this->metal = Metal::create([
            'nombre' => 'Oro de prueba',
            'simbolo' => 'Au',
            'estado' => 'activo',
        ]);

        $this->pureza = Pureza::create([
            'metal_id' => $this->metal->id,
            'codigo' => '10K',
            'nombre' => 'Oro 10 quilates',
        ]);

        $this->inventario = app(InventarioService::class);
    }

    private function producto(
        string $tipo = 'individual'
    ): Producto {
        return Producto::create([
            'categoria_id' => $this->categoria->id,
            'metal_id' => $this->metal->id,
            'pureza_id' => $this->pureza->id,
            'codigo' => 'INV-TEST-001',
            'nombre' => 'Producto de prueba',
            'tipo' => $tipo,
            'estado' => 'activo',
            'precio_base' => '500.0000',
            'stock_minimo' => '0.0000',
        ]);
    }

    private function estadoId(string $codigo): int
    {
        return (int) DB::table('estados_inventario')
            ->where('codigo', $codigo)
            ->value('id');
    }

    private function stock(
        Producto $producto,
        string $estado = 'disponible'
    ): string {
        $cantidad = Existencia::query()
            ->where('producto_id', $producto->id)
            ->where('sucursal_id', $this->sucursalId)
            ->where('estado_inventario_id', $this->estadoId($estado))
            ->value('cantidad');

        return bcadd((string) ($cantidad ?? '0'), '0', 4);
    }

    private function pieza(Producto $producto): Pieza
    {
        return $this->inventario->registrarPieza(
            $producto,
            [
                'codigo_pieza' => 'PIEZA-TEST-001',
                'peso_gramos' => '2.500',
            ],
            $this->admin
        );
    }

    public function test_c01_registra_pieza_individual(): void
    {
        $producto = $this->producto();
        $pieza = $this->pieza($producto);

        $this->assertSame('PIEZA-TEST-001', $pieza->codigo_pieza);
        $this->assertSame('2.500', $pieza->peso_gramos);
        $this->assertEquals($producto->id, $pieza->producto_id);
        $this->assertEquals($this->sucursalId, $pieza->sucursal_id);
        $this->assertEquals($producto->metal_id, $pieza->metal_id);
        $this->assertEquals($producto->pureza_id, $pieza->pureza_id);
        $this->assertEquals(
            $this->estadoId('disponible'),
            $pieza->estado_inventario_id
        );

        $this->assertDatabaseHas('movimientos_inventario', [
            'producto_id' => $producto->id,
            'pieza_id' => $pieza->id,
            'sucursal_id' => $this->sucursalId,
            'usuario_id' => $this->admin->id,
            'tipo_movimiento' => 'entrada',
            'estado_origen_id' => null,
            'estado_destino_id' => $this->estadoId('disponible'),
            'cantidad' => '1.0000',
        ]);

        $this->assertDatabaseCount('existencias', 0);
    }

    public function test_c02_rechaza_codigo_duplicado(): void
    {
        $producto = $this->producto();

        $this->pieza($producto);

        try {
            $this->pieza($producto);
            $this->fail('Se permitio un codigo duplicado.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('codigo_pieza', $e->errors());
        }

        $this->assertDatabaseCount('piezas', 1);
        $this->assertDatabaseCount('movimientos_inventario', 1);
    }

    public function test_c03_registra_entrada_por_cantidad(): void
    {
        $producto = $this->producto('por_cantidad');

        $movimiento = $this->inventario->registrarEntrada(
            $producto, '10', $this->admin
        );

        $this->assertSame('10.0000', $this->stock($producto));
        $this->assertSame('entrada', $movimiento->tipo_movimiento);
        $this->assertSame('10.0000', $movimiento->cantidad);
        $this->assertNull($movimiento->pieza_id);
        $this->assertDatabaseCount('piezas', 0);
    }

    public function test_c04_rechaza_unidades_fraccionarias(): void
    {
        $producto = $this->producto('por_cantidad');

        try {
            $this->inventario->registrarEntrada(
                $producto, '1.5000', $this->admin
            );

            $this->fail('Se permitieron unidades fraccionarias.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('cantidad', $e->errors());
        }

        $this->assertSame('0.0000', $this->stock($producto));
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    public function test_c05_conserva_precision_decimal(): void
    {
        $producto = $this->producto('por_peso');

        $this->inventario->registrarEntrada(
            $producto, '0.1001', $this->admin
        );

        $this->inventario->registrarEntrada(
            $producto, '0.2002', $this->admin
        );

        $this->assertSame('0.3003', $this->stock($producto));
        $this->assertDatabaseCount('movimientos_inventario', 2);
    }

    public function test_c06_realiza_ajuste_positivo(): void
    {
        $producto = $this->producto('por_cantidad');

        $this->inventario->registrarEntrada(
            $producto, '10', $this->admin
        );

        $movimiento = $this->inventario->ajustarExistencia(
            $producto,
            '3',
            'positivo',
            $this->admin,
            'Correccion de conteo fisico.'
        );

        $this->assertSame('13.0000', $this->stock($producto));
        $this->assertSame('ajuste', $movimiento->tipo_movimiento);
        $this->assertNull($movimiento->estado_origen_id);
        $this->assertEquals(
            $this->estadoId('disponible'),
            $movimiento->estado_destino_id
        );
    }

    public function test_c07_realiza_ajuste_negativo(): void
    {
        $producto = $this->producto('por_cantidad');

        $this->inventario->registrarEntrada(
            $producto, '10', $this->admin
        );

        $movimiento = $this->inventario->ajustarExistencia(
            $producto,
            '4',
            'negativo',
            $this->admin,
            'Correccion de conteo fisico.'
        );

        $this->assertSame('6.0000', $this->stock($producto));
        $this->assertSame('ajuste', $movimiento->tipo_movimiento);
        $this->assertNull($movimiento->estado_destino_id);
        $this->assertEquals(
            $this->estadoId('disponible'),
            $movimiento->estado_origen_id
        );
    }

    public function test_c08_impide_existencias_negativas(): void
    {
        $producto = $this->producto('por_cantidad');

        $this->inventario->registrarEntrada(
            $producto, '5', $this->admin
        );

        try {
            $this->inventario->ajustarExistencia(
                $producto,
                '6',
                'negativo',
                $this->admin,
                'Ajuste de prueba.'
            );

            $this->fail('Se permitio inventario negativo.');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }

        $this->assertSame('5.0000', $this->stock($producto));
        $this->assertDatabaseCount('movimientos_inventario', 1);
    }

    public function test_c09_envia_pieza_a_reparacion(): void
    {
        $producto = $this->producto();
        $pieza = $this->pieza($producto);

        $movimiento = $this->inventario->cambiarEstadoPieza(
            $pieza,
            'reparacion',
            $this->admin,
            'Requiere reparacion.'
        );

        $this->assertEquals(
            $this->estadoId('reparacion'),
            $pieza->fresh()->estado_inventario_id
        );

        $this->assertSame('ajuste', $movimiento->tipo_movimiento);
        $this->assertSame('1.0000', $movimiento->cantidad);
    }

    public function test_c10_recupera_pieza_reparada(): void
    {
        $producto = $this->producto();
        $pieza = $this->pieza($producto);

        $this->inventario->cambiarEstadoPieza(
            $pieza,
            'reparacion',
            $this->admin,
            'Requiere reparacion.'
        );

        $this->inventario->cambiarEstadoPieza(
            $pieza->fresh(),
            'disponible',
            $this->admin,
            'Reparacion terminada.'
        );

        $this->assertEquals(
            $this->estadoId('disponible'),
            $pieza->fresh()->estado_inventario_id
        );

        $this->assertDatabaseCount('movimientos_inventario', 3);
    }

    public function test_c11_rechaza_venta_manual(): void
    {
        $producto = $this->producto();
        $pieza = $this->pieza($producto);

        try {
            $this->inventario->cambiarEstadoPieza(
                $pieza,
                'vendido',
                $this->admin,
                'Intento de venta manual.'
            );

            $this->fail('Se permitio una venta manual.');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }

        $this->assertEquals(
            $this->estadoId('disponible'),
            $pieza->fresh()->estado_inventario_id
        );

        $this->assertDatabaseCount('movimientos_inventario', 1);
    }

    public function test_c12_impide_modificar_movimiento(): void
    {
        $producto = $this->producto('por_cantidad');

        $movimiento = $this->inventario->registrarEntrada(
            $producto, '5', $this->admin
        );

        $this->expectException(LogicException::class);

        $movimiento->cantidad = '20.0000';
        $movimiento->save();
    }

    public function test_c13_impide_eliminar_movimiento(): void
    {
        $producto = $this->producto('por_cantidad');

        $movimiento = $this->inventario->registrarEntrada(
            $producto, '5', $this->admin
        );

        $this->expectException(LogicException::class);

        $movimiento->delete();
    }

    public function test_c14_libera_bloqueo_compartido(): void
    {
        $producto = $this->producto('por_cantidad');

        $this->inventario->registrarEntrada(
            $producto, '5', $this->admin
        );

        $nombre = 'joysys:catalogos:' . sha1(
            DB::connection()->getDatabaseName()
        );

        $resultado = DB::selectOne(
            'SELECT IS_FREE_LOCK(?) AS libre',
            [$nombre],
            false
        );

        $this->assertEquals(1, $resultado->libre);
    }


    private function crearEmpleadoInventario(
        bool $asignarSucursal = true,
        ?int $sucursalId = null
    ): User {
        $rolId = DB::table('roles')->insertGetId([
            'nombre' => 'Empleado',
            'slug' => 'empleado',
            'estado' => 'activo',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ([
            'inventario.ver',
            'incidencias.ver',
            'incidencias.reportar',
        ] as $clave) {
            $permisoId = DB::table('permisos')
                ->where('clave', $clave)
                ->value('id');

            DB::table('rol_permiso')->insert([
                'rol_id' => $rolId,
                'permiso_id' => $permisoId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $empleado = User::factory()->create([
            'role_id' => $rolId,
        ]);

        if ($asignarSucursal) {
            DB::table('empleado_sucursal')->insert([
                'usuario_id' => $empleado->id,
                'sucursal_id' => $sucursalId ?? $this->sucursalId,
                'es_principal' => true,
                'fecha_inicio' => now('America/Mexico_City')
                    ->subDay()->toDateString(),
                'fecha_fin' => null,
                'estado' => 'activo',
                'asignado_por' => $this->admin->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $empleado;
    }

    private function otraSucursal(): int
    {
        return DB::table('sucursales')->insertGetId([
            'nombre' => 'Sucursal alternativa',
            'codigo' => 'SUC-INV-OTRA',
            'estado' => 'activa',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function servicioIncidencias():
        \App\Services\Inventario\IncidenciaService
    {
        return app(
            \App\Services\Inventario\IncidenciaService::class
        );
    }

    public function test_c15_invitado_no_accede_a_inventario(): void
    {
        foreach ([
            'inventario.index',
            'inventario.piezas',
            'inventario.existencias',
            'inventario.movimientos',
            'inventario.entradas.create',
            'inventario.salidas.create',
            'inventario.ajustes.create',
            'incidencias.index',
            'incidencias.create',
        ] as $ruta) {
            $this->get(route($ruta))
                ->assertRedirect(route('login'));
        }
    }

    public function test_c16_admin_consulta_vistas(): void
    {
        $producto = $this->producto();
        $this->pieza($producto);

        $this->actingAs($this->admin);

        foreach ([
            'inventario.index',
            'inventario.piezas',
            'inventario.existencias',
            'inventario.movimientos',
            'inventario.entradas.create',
            'inventario.salidas.create',
            'inventario.ajustes.create',
            'incidencias.index',
            'incidencias.create',
        ] as $ruta) {
            $this->get(route($ruta))->assertOk();
        }
    }

    public function test_c17_empleado_consulta_pero_no_modifica(): void
    {
        $empleado = $this->crearEmpleadoInventario();
        $producto = $this->producto('por_cantidad');

        $this->actingAs($empleado);

        $this->get(route('inventario.index'))->assertOk();
        $this->get(route('inventario.movimientos'))->assertOk();

        foreach (['entradas', 'salidas', 'ajustes'] as $operacion) {
            $this->get(route(
                "inventario.{$operacion}.create"
            ))->assertForbidden();

            $this->post(route(
                "inventario.{$operacion}.store"
            ), [
                'producto_id' => $producto->id,
                'cantidad' => '1',
            ])->assertForbidden();
        }

        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    public function test_c18_usuario_sin_sucursal_no_opera(): void
    {
        $empleado = $this->crearEmpleadoInventario(false);

        $this->actingAs($empleado);

        $this->get(route('inventario.index'))->assertForbidden();
        $this->get(route('incidencias.index'))->assertForbidden();
        $this->get(route('incidencias.create'))->assertForbidden();
    }

    public function test_c19_registra_pieza_mediante_http(): void
    {
        $producto = $this->producto();

        $this->actingAs($this->admin)
            ->post(route('inventario.entradas.store'), [
                'producto_id' => $producto->id,
                'codigo_pieza' => 'HTTP-PIEZA-001',
                'peso_gramos' => '3.250',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('inventario.index'));

        $this->assertDatabaseHas('piezas', [
            'codigo_pieza' => 'HTTP-PIEZA-001',
            'producto_id' => $producto->id,
            'sucursal_id' => $this->sucursalId,
            'peso_gramos' => '3.250',
        ]);

        $this->assertDatabaseCount('movimientos_inventario', 1);
    }

    public function test_c20_registra_existencias_mediante_http(): void
    {
        $producto = $this->producto('por_cantidad');

        $this->actingAs($this->admin)
            ->post(route('inventario.entradas.store'), [
                'producto_id' => $producto->id,
                'cantidad' => '12',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('inventario.index'));

        $this->assertSame('12.0000', $this->stock($producto));
    }

    public function test_c21_rechaza_sucursal_manipulada(): void
    {
        $producto = $this->producto('por_cantidad');

        $this->actingAs($this->admin)
            ->from(route('inventario.entradas.create'))
            ->post(route('inventario.entradas.store'), [
                'producto_id' => $producto->id,
                'cantidad' => '5',
                'sucursal_id' => $this->otraSucursal(),
            ])
            ->assertSessionHasErrors('sucursal_id');

        $this->assertDatabaseCount('existencias', 0);
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    public function test_c22_rechaza_actor_manipulado(): void
    {
        $producto = $this->producto('por_cantidad');
        $empleado = $this->crearEmpleadoInventario();

        $this->actingAs($this->admin)
            ->from(route('inventario.entradas.create'))
            ->post(route('inventario.entradas.store'), [
                'producto_id' => $producto->id,
                'cantidad' => '5',
                'usuario_id' => $empleado->id,
            ])
            ->assertSessionHasErrors('usuario_id');

        $this->assertDatabaseCount('existencias', 0);
    }

    public function test_c23_rechaza_cantidad_en_producto_individual(): void
    {
        $producto = $this->producto();

        $this->actingAs($this->admin)
            ->from(route('inventario.entradas.create'))
            ->post(route('inventario.entradas.store'), [
                'producto_id' => $producto->id,
                'codigo_pieza' => 'HTTP-INVALIDA',
                'cantidad' => '3',
            ])
            ->assertSessionHasErrors('cantidad');

        $this->assertDatabaseCount('piezas', 0);
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    public function test_c24_empleado_reporta_incidencia(): void
    {
        $empleado = $this->crearEmpleadoInventario();
        $producto = $this->producto();

        $this->actingAs($empleado)
            ->post(route('incidencias.store'), [
                'producto_id' => $producto->id,
                'tipo_incidencia' => 'faltante',
                'descripcion' => 'Diferencia en conteo fisico.',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('incidencias.index'));

        $this->assertDatabaseHas('incidencias_inventario', [
            'producto_id' => $producto->id,
            'sucursal_id' => $this->sucursalId,
            'usuario_reporta_id' => $empleado->id,
            'tipo_incidencia' => 'faltante',
            'estado' => 'abierta',
        ]);

        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    public function test_c25_empleado_no_resuelve_incidencias(): void
    {
        $empleado = $this->crearEmpleadoInventario();
        $producto = $this->producto();

        $incidencia = $this->servicioIncidencias()->reportar(
            $producto,
            [
                'tipo_incidencia' => 'faltante',
                'descripcion' => 'Incidencia de prueba.',
            ],
            $empleado
        );

        $this->actingAs($empleado)
            ->patch(route('incidencias.estado', $incidencia), [
                'estado' => 'en_revision',
            ])
            ->assertForbidden();

        $this->assertSame('abierta', $incidencia->fresh()->estado);
    }

    public function test_c26_admin_resuelve_incidencia(): void
    {
        $producto = $this->producto();

        $incidencia = $this->servicioIncidencias()->reportar(
            $producto,
            [
                'tipo_incidencia' => 'danado',
                'descripcion' => 'Revision de mercancia.',
            ],
            $this->admin
        );

        $this->actingAs($this->admin);

        $this->patch(route('incidencias.estado', $incidencia), [
            'estado' => 'en_revision',
        ])->assertSessionHasNoErrors()
          ->assertRedirect(route('incidencias.index'));

        $this->assertSame(
            'en_revision',
            $incidencia->fresh()->estado
        );

        $this->patch(route('incidencias.estado', $incidencia), [
            'estado' => 'resuelta',
        ])->assertSessionHasNoErrors()
          ->assertRedirect(route('incidencias.index'));

        $this->assertSame('resuelta', $incidencia->fresh()->estado);
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    public function test_c27_rechaza_saltar_revision(): void
    {
        $producto = $this->producto();

        $incidencia = $this->servicioIncidencias()->reportar(
            $producto,
            [
                'tipo_incidencia' => 'otro',
                'descripcion' => 'Incidencia de prueba.',
            ],
            $this->admin
        );

        $this->actingAs($this->admin)
            ->from(route('incidencias.index'))
            ->patch(route('incidencias.estado', $incidencia), [
                'estado' => 'resuelta',
            ])
            ->assertSessionHasErrors('estado');

        $this->assertSame('abierta', $incidencia->fresh()->estado);
    }

    public function test_c28_rechaza_tipo_incidencia_invalido(): void
    {
        $producto = $this->producto();

        $this->actingAs($this->admin)
            ->from(route('incidencias.create'))
            ->post(route('incidencias.store'), [
                'producto_id' => $producto->id,
                'tipo_incidencia' => 'invalido',
                'descripcion' => 'Incidencia invalida.',
            ])
            ->assertSessionHasErrors('tipo_incidencia');

        $this->assertDatabaseCount('incidencias_inventario', 0);
    }

    public function test_c29_reporte_no_modifica_existencias(): void
    {
        $producto = $this->producto('por_cantidad');

        $this->inventario->registrarEntrada(
            $producto, '10', $this->admin
        );

        $this->servicioIncidencias()->reportar(
            $producto,
            [
                'tipo_incidencia' => 'faltante',
                'descripcion' => 'Faltan dos unidades.',
            ],
            $this->admin
        );

        $this->assertSame('10.0000', $this->stock($producto));
        $this->assertDatabaseCount('movimientos_inventario', 1);
        $this->assertDatabaseCount('incidencias_inventario', 1);
    }

    public function test_c30_transfiere_existencias_entre_estados(): void
    {
        $producto = $this->producto('por_peso');

        $this->inventario->registrarEntrada(
            $producto, '10.1234', $this->admin
        );

        $movimiento = $this->inventario->cambiarEstadoExistencia(
            $producto,
            '2.0001',
            'disponible',
            'danado',
            $this->admin,
            'Mercancia danada.'
        );

        $this->assertSame('8.1233', $this->stock($producto));
        $this->assertSame(
            '2.0001',
            $this->stock($producto, 'danado')
        );

        $this->assertSame('ajuste', $movimiento->tipo_movimiento);
        $this->assertEquals(
            $this->estadoId('danado'),
            $movimiento->estado_destino_id
        );
    }

    public function test_c31_aisla_piezas_de_otras_sucursales(): void
    {
        $producto = $this->producto();
        $pieza = $this->pieza($producto);

        $empleado = $this->crearEmpleadoInventario(
            true,
            $this->otraSucursal()
        );

        $this->assertSame(
            0,
            $this->inventario->listarPiezas($empleado)->total()
        );

        $this->assertSame(
            0,
            $this->inventario->listarMovimientos($empleado)->total()
        );

        $this->assertSame(
            1,
            $this->inventario->listarPiezas($this->admin)->total()
        );

        $this->actingAs($empleado)
            ->get(route('inventario.piezas'))
            ->assertOk()
            ->assertDontSee($pieza->codigo_pieza);
    }

    public function test_c32_revierte_entrada_si_falla_kardex(): void
    {
        $producto = $this->producto('por_cantidad');

        $original = MovimientoInventario::getEventDispatcher();

        MovimientoInventario::setEventDispatcher(clone $original);

        MovimientoInventario::creating(function () {
            throw new \RuntimeException('FALLO-KARDEX-PRUEBA');
        });

        try {
            $this->inventario->registrarEntrada(
                $producto, '5', $this->admin
            );

            $this->fail('No se produjo el fallo simulado.');
        } catch (\RuntimeException $e) {
            $this->assertSame(
                'FALLO-KARDEX-PRUEBA',
                $e->getMessage()
            );
        } finally {
            MovimientoInventario::setEventDispatcher($original);
        }

        $this->assertDatabaseCount('existencias', 0);
        $this->assertDatabaseCount('movimientos_inventario', 0);

        $nombre = 'joysys:catalogos:' . sha1(
            DB::connection()->getDatabaseName()
        );

        $resultado = DB::selectOne(
            'SELECT IS_FREE_LOCK(?) AS libre',
            [$nombre],
            false
        );

        $this->assertEquals(1, $resultado->libre);

        $this->inventario->registrarEntrada(
            $producto, '3', $this->admin
        );

        $this->assertSame('3.0000', $this->stock($producto));
    }

    public function test_c33_no_existen_rutas_de_eliminacion(): void
    {
        foreach (app('router')->getRoutes() as $ruta) {
            $nombre = $ruta->getName() ?? '';

            if (
                str_starts_with($nombre, 'inventario.') ||
                str_starts_with($nombre, 'incidencias.')
            ) {
                $this->assertNotContains('DELETE', $ruta->methods());
            }
        }
    }



    public function test_c34_salida_administrativa_por_cantidad(): void
    {
        $producto = $this->producto('por_cantidad');

        $this->inventario->registrarEntrada(
            $producto, '10', $this->admin
        );

        $movimiento = $this->inventario->registrarSalida(
            $producto, '3', $this->admin, 'Retiro administrativo'
        );

        $this->assertSame('7.0000', $this->stock($producto));
        $this->assertSame('salida', $movimiento->tipo_movimiento);
        $this->assertSame('3.0000', $movimiento->cantidad);
        $this->assertEquals(
            $this->estadoId('disponible'),
            $movimiento->estado_origen_id
        );
        $this->assertNull($movimiento->estado_destino_id);
        $this->assertNull($movimiento->pieza_id);
        $this->assertSame(
            '0.0000',
            $this->stock($producto, 'perdido')
        );
    }

    public function test_c35_salida_administrativa_por_peso(): void
    {
        $producto = $this->producto('por_peso');

        $this->inventario->registrarEntrada(
            $producto, '0.3003', $this->admin
        );

        $movimiento = $this->inventario->registrarSalida(
            $producto, '0.1001', $this->admin, 'Retiro de material'
        );

        $this->assertSame('0.2002', $this->stock($producto));
        $this->assertSame('0.1001', $movimiento->cantidad);
        $this->assertSame('salida', $movimiento->tipo_movimiento);
        $this->assertNull($movimiento->estado_destino_id);
    }

    public function test_c36_rechaza_salida_administrativa_sin_stock(): void
    {
        $producto = $this->producto('por_cantidad');

        $this->inventario->registrarEntrada(
            $producto, '5', $this->admin
        );

        try {
            $this->inventario->registrarSalida(
                $producto, '6', $this->admin, 'Retiro'
            );

            $this->fail('Se permitio una salida sin stock suficiente.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('cantidad', $e->errors());
        }

        $this->assertSame('5.0000', $this->stock($producto));
        $this->assertDatabaseCount('movimientos_inventario', 1);
    }

    public function test_c37_rechaza_salida_administrativa_individual(): void
    {
        $producto = $this->producto();
        $pieza = $this->pieza($producto);

        try {
            $this->inventario->registrarSalida(
                $producto, '1', $this->admin, 'Retiro'
            );

            $this->fail('Se permitio una salida individual ordinaria.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('producto_id', $e->errors());
        }

        $this->assertEquals(
            $this->estadoId('disponible'),
            $pieza->fresh()->estado_inventario_id
        );

        $this->assertDatabaseCount('movimientos_inventario', 1);
    }

    public function test_c38_perdida_y_recuperacion_de_existencias(): void
    {
        $producto = $this->producto('por_cantidad');

        $this->inventario->registrarEntrada(
            $producto, '10', $this->admin
        );

        $perdida = $this->inventario->cambiarEstadoExistencia(
            $producto,
            '3',
            'disponible',
            'perdido',
            $this->admin,
            'Mercancia no localizada'
        );

        $this->assertSame('salida', $perdida->tipo_movimiento);
        $this->assertSame('7.0000', $this->stock($producto));
        $this->assertSame(
            '3.0000',
            $this->stock($producto, 'perdido')
        );

        $recuperacion = $this->inventario->cambiarEstadoExistencia(
            $producto,
            '2',
            'perdido',
            'disponible',
            $this->admin,
            'Mercancia localizada'
        );

        $this->assertSame('ajuste', $recuperacion->tipo_movimiento);
        $this->assertSame('9.0000', $this->stock($producto));
        $this->assertSame(
            '1.0000',
            $this->stock($producto, 'perdido')
        );
        $this->assertDatabaseCount('movimientos_inventario', 3);
    }

    public function test_c39_codigo_de_pieza_inmutable(): void
    {
        $pieza = $this->pieza($this->producto());
        $codigo = $pieza->codigo_pieza;

        try {
            $pieza->update([
                'codigo_pieza' => 'CODIGO-MODIFICADO',
            ]);

            $this->fail('Se modifico el codigo de una pieza.');
        } catch (\LogicException $e) {
            $this->assertStringContainsString(
                'inmutable',
                $e->getMessage()
            );
        }

        $this->assertSame(
            $codigo,
            $pieza->fresh()->codigo_pieza
        );
    }

    public function test_c40_identidad_de_pieza_inmutable(): void
    {
        $producto = $this->producto();
        $pieza = $this->pieza($producto);

        $cambios = [
            'producto_id' => $producto->id + 1,
            'metal_id' => null,
            'pureza_id' => null,
        ];

        foreach ($cambios as $campo => $valor) {
            $actual = $pieza->fresh();
            $original = $actual->{$campo};

            try {
                $actual->update([$campo => $valor]);

                $this->fail("Se modifico el campo {$campo}.");
            } catch (\LogicException $e) {
                $this->assertStringContainsString(
                    'inmutable',
                    $e->getMessage()
                );
            }

            $this->assertSame(
                $original,
                $pieza->fresh()->{$campo}
            );
        }
    }

    public function test_c41_impide_eliminar_pieza(): void
    {
        $pieza = $this->pieza($this->producto());

        try {
            $pieza->delete();

            $this->fail('Se elimino fisicamente una pieza.');
        } catch (\LogicException $e) {
            $this->assertStringContainsString(
                'eliminarse',
                $e->getMessage()
            );
        }

        $this->assertDatabaseHas('piezas', [
            'id' => $pieza->id,
        ]);
    }

    public function test_c42_limite_maximo_de_existencias(): void
    {
        $producto = $this->producto('por_peso');

        $this->inventario->registrarEntrada(
            $producto, '99999999.9999', $this->admin
        );

        try {
            $this->inventario->registrarEntrada(
                $producto, '0.0001', $this->admin
            );

            $this->fail('Se excedio la capacidad de existencias.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('cantidad', $e->errors());
        }

        $this->assertSame(
            '99999999.9999',
            $this->stock($producto)
        );

        $this->assertDatabaseCount('movimientos_inventario', 1);
    }

    public function test_c43_limites_de_peso_individual(): void
    {
        $producto = $this->producto();

        $pieza = $this->inventario->registrarPieza(
            $producto,
            [
                'codigo_pieza' => 'PESO-MAXIMO',
                'peso_gramos' => '99999.999',
            ],
            $this->admin
        );

        $this->assertSame('99999.999', $pieza->peso_gramos);

        try {
            $this->inventario->registrarPieza(
                $producto,
                [
                    'codigo_pieza' => 'PESO-INVALIDO',
                    'peso_gramos' => '100000.000',
                ],
                $this->admin
            );

            $this->fail('Se permitio un peso fuera del limite.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('peso_gramos', $e->errors());
        }

        $this->assertDatabaseCount('piezas', 1);
        $this->assertDatabaseCount('movimientos_inventario', 1);
    }

    public function test_c44_rechaza_entrada_de_producto_inactivo(): void
    {
        $producto = $this->producto('por_cantidad');

        $producto->update(['estado' => 'inactivo']);

        try {
            $this->inventario->registrarEntrada(
                $producto, '5', $this->admin
            );

            $this->fail('Se registro inventario de un producto inactivo.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('producto_id', $e->errors());
        }

        $this->assertDatabaseCount('existencias', 0);
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    public function test_c45_justificacion_obligatoria_en_salidas_y_ajustes(): void
    {
        $producto = $this->producto('por_cantidad');

        $this->inventario->registrarEntrada(
            $producto, '10', $this->admin
        );

        $operaciones = [
            fn () => $this->inventario->registrarSalida(
                $producto, '2', $this->admin, '   '
            ),
            fn () => $this->inventario->ajustarExistencia(
                $producto, '2', 'negativo', $this->admin, ''
            ),
            fn () => $this->inventario->cambiarEstadoExistencia(
                $producto,
                '2',
                'disponible',
                'perdido',
                $this->admin,
                null
            ),
        ];

        foreach ($operaciones as $operacion) {
            try {
                $operacion();

                $this->fail('Se permitio una operacion sin justificacion.');
            } catch (\Illuminate\Validation\ValidationException $e) {
                $this->assertArrayHasKey(
                    'observaciones',
                    $e->errors()
                );
            }
        }

        $this->assertSame('10.0000', $this->stock($producto));
        $this->assertDatabaseCount('movimientos_inventario', 1);
    }



    public function test_c46_admite_los_siete_tipos_de_incidencias(): void
    {
        $producto = $this->producto();

        $servicio = app(
            \App\Services\Inventario\IncidenciaService::class
        );

        $tipos = [
            'faltante',
            'sobrante',
            'danado',
            'perdido',
            'diferencia_peso',
            'error_registro',
            'otro',
        ];

        foreach ($tipos as $tipo) {
            $incidencia = $servicio->reportar(
                $producto,
                [
                    'tipo_incidencia' => $tipo,
                    'descripcion' => 'Incidencia de prueba',
                ],
                $this->admin
            );

            $this->assertSame(
                $tipo,
                $incidencia->tipo_incidencia
            );

            $this->assertSame(
                'abierta',
                $incidencia->estado
            );

            $this->assertEquals(
                $this->sucursalId,
                $incidencia->sucursal_id
            );
        }

        $this->assertDatabaseCount(
            'incidencias_inventario',
            7
        );

        $this->assertDatabaseCount('piezas', 0);
        $this->assertDatabaseCount('existencias', 0);
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    public function test_c47_rechaza_tipo_de_incidencia_invalido(): void
    {
        $producto = $this->producto();

        $servicio = app(
            \App\Services\Inventario\IncidenciaService::class
        );

        try {
            $servicio->reportar(
                $producto,
                [
                    'tipo_incidencia' => 'tipo_inexistente',
                    'descripcion' => 'Prueba de validacion',
                ],
                $this->admin
            );

            $this->fail('Se permitio una incidencia invalida.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey(
                'tipo_incidencia',
                $e->errors()
            );
        }

        $this->assertDatabaseCount(
            'incidencias_inventario',
            0
        );
    }

    public function test_c48_busqueda_de_productos_por_codigo_y_nombre(): void
    {
        $producto = $this->producto();

        foreach (['INV-TEST', 'Producto de prueba'] as $buscar) {
            $resultado = $this->inventario->listarProductos(
                $this->admin,
                ['buscar' => $buscar]
            );

            $this->assertSame(1, $resultado->total());

            $this->assertEquals(
                $producto->id,
                $resultado->items()[0]->id
            );
        }

        $resultado = $this->inventario->listarProductos(
            $this->admin,
            ['buscar' => 'NO-EXISTE-999']
        );

        $this->assertSame(0, $resultado->total());
    }

    public function test_c49_busqueda_literal_de_caracteres_especiales(): void
    {
        $producto = $this->producto();

        $producto->update([
            'codigo' => 'INV_100%',
        ]);

        $otro = $producto->replicate();

        $otro->codigo = 'INVX100Y';
        $otro->save();

        foreach (['_', '%', 'INV_100%'] as $buscar) {
            $resultado = $this->inventario->listarProductos(
                $this->admin,
                ['buscar' => $buscar]
            );

            $this->assertSame(1, $resultado->total());

            $this->assertEquals(
                $producto->id,
                $resultado->items()[0]->id
            );
        }
    }

    public function test_c50_filtros_de_categoria_y_tipo(): void
    {
        $producto = $this->producto('por_cantidad');

        $otro = $producto->replicate();
        $otro->codigo = 'INV-OTRO-TIPO';
        $otro->tipo = 'por_peso';
        $otro->save();

        $resultado = $this->inventario->listarProductos(
            $this->admin,
            [
                'categoria_id' => $producto->categoria_id,
                'tipo' => 'por_cantidad',
            ]
        );

        $this->assertSame(1, $resultado->total());

        $this->assertEquals(
            $producto->id,
            $resultado->items()[0]->id
        );

        $resultado = $this->inventario->listarProductos(
            $this->admin,
            [
                'categoria_id' => $producto->categoria_id,
                'tipo' => 'individual',
            ]
        );

        $this->assertSame(0, $resultado->total());
    }

    public function test_c51_filtros_de_nivel_de_stock(): void
    {
        $vacio = $this->producto('por_cantidad');

        $vacio->update([
            'stock_minimo' => '5.0000',
        ]);

        $bajo = $vacio->replicate();
        $bajo->codigo = 'INV-STOCK-BAJO';
        $bajo->save();

        $suficiente = $vacio->replicate();
        $suficiente->codigo = 'INV-STOCK-SUFICIENTE';
        $suficiente->save();

        $this->inventario->registrarEntrada(
            $bajo, '5', $this->admin
        );

        $this->inventario->registrarEntrada(
            $suficiente, '6', $this->admin
        );

        $casos = [
            'sin_existencias' => $vacio->id,
            'bajo' => $bajo->id,
            'suficiente' => $suficiente->id,
        ];

        foreach ($casos as $nivel => $id) {
            $resultado = $this->inventario->listarProductos(
                $this->admin,
                ['nivel_stock' => $nivel]
            );

            $this->assertSame(1, $resultado->total());

            $this->assertEquals(
                $id,
                $resultado->items()[0]->id
            );

            $this->assertSame(
                $nivel,
                $resultado->items()[0]->indicador_stock
            );
        }
    }

    public function test_c52_filtra_antes_de_paginar(): void
    {
        $base = $this->producto();

        $base->update([
            'nombre' => 'AAA Producto sin coincidencia',
        ]);

        for ($i = 1; $i <= 20; $i++) {
            $producto = $base->replicate();

            $producto->codigo = 'PAGINACION-' . $i;

            $producto->nombre = sprintf(
                'ZZZ Objetivo %02d',
                $i
            );

            $producto->save();
        }

        $resultado = $this->inventario->listarProductos(
            $this->admin,
            ['buscar' => 'ZZZ Objetivo']
        );

        $this->assertSame(20, $resultado->total());
        $this->assertSame(15, $resultado->perPage());
        $this->assertSame(15, $resultado->count());
        $this->assertSame(2, $resultado->lastPage());

        foreach ($resultado->items() as $producto) {
            $this->assertStringContainsString(
                'ZZZ Objetivo',
                $producto->nombre
            );
        }
    }

    public function test_c53_filtros_de_piezas(): void
    {
        $producto = $this->producto();
        $pieza = $this->pieza($producto);

        $this->inventario->cambiarEstadoPieza(
            $pieza,
            'reparacion',
            $this->admin,
            'Reparacion de prueba'
        );

        $resultado = $this->inventario->listarPiezas(
            $this->admin,
            [
                'buscar' => $pieza->codigo_pieza,
                'estado' => 'reparacion',
            ]
        );

        $this->assertSame(1, $resultado->total());

        $this->assertEquals(
            $pieza->id,
            $resultado->items()[0]->id
        );

        $resultado = $this->inventario->listarPiezas(
            $this->admin,
            ['estado' => 'disponible']
        );

        $this->assertSame(0, $resultado->total());
    }

    public function test_c54_filtros_de_existencias(): void
    {
        $producto = $this->producto('por_cantidad');

        $this->inventario->registrarEntrada(
            $producto, '10', $this->admin
        );

        $this->inventario->cambiarEstadoExistencia(
            $producto,
            '3',
            'disponible',
            'danado',
            $this->admin,
            'Mercancia danada'
        );

        $resultado = $this->inventario->listarExistencias(
            $this->admin,
            [
                'buscar' => $producto->codigo,
                'tipo' => 'por_cantidad',
                'estado' => 'danado',
            ]
        );

        $this->assertSame(1, $resultado->total());

        $this->assertSame(
            '3.0000',
            $resultado->items()[0]->cantidad
        );

        $resultado = $this->inventario->listarExistencias(
            $this->admin,
            ['tipo' => 'por_peso']
        );

        $this->assertSame(0, $resultado->total());
    }

    public function test_c55_filtros_de_movimientos(): void
    {
        $producto = $this->producto('por_cantidad');

        $this->inventario->registrarEntrada(
            $producto, '10', $this->admin
        );

        $this->inventario->registrarSalida(
            $producto, '2', $this->admin, 'Salida de prueba'
        );

        $this->inventario->ajustarExistencia(
            $producto,
            '1',
            'positivo',
            $this->admin,
            'Ajuste de prueba'
        );

        foreach (['entrada', 'salida', 'ajuste'] as $tipo) {
            $resultado = $this->inventario->listarMovimientos(
                $this->admin,
                [
                    'buscar' => $producto->codigo,
                    'tipo_movimiento' => $tipo,
                ]
            );

            $this->assertSame(1, $resultado->total());

            $this->assertSame(
                $tipo,
                $resultado->items()[0]->tipo_movimiento
            );
        }
    }

    public function test_c56_filtro_de_fechas_en_horario_local(): void
    {
        $producto = $this->producto('por_cantidad');

        $movimiento = $this->inventario->registrarEntrada(
            $producto, '10', $this->admin
        );

        $fecha = \Carbon\CarbonImmutable::parse(
            $movimiento->created_at,
            'UTC'
        )->setTimezone('America/Mexico_City');

        $resultado = $this->inventario->listarMovimientos(
            $this->admin,
            [
                'fecha_desde' => $fecha->format('Y-m-d'),
                'fecha_hasta' => $fecha->format('Y-m-d'),
            ]
        );

        $this->assertSame(1, $resultado->total());

        $resultado = $this->inventario->listarMovimientos(
            $this->admin,
            [
                'fecha_desde' => $fecha->addDay()->format('Y-m-d'),
            ]
        );

        $this->assertSame(0, $resultado->total());
    }

    public function test_c57_filtros_de_incidencias(): void
    {
        $producto = $this->producto();

        $servicio = app(
            \App\Services\Inventario\IncidenciaService::class
        );

        $primera = $servicio->reportar(
            $producto,
            [
                'tipo_incidencia' => 'faltante',
                'descripcion' => 'Primera incidencia',
            ],
            $this->admin
        );

        $servicio->reportar(
            $producto,
            [
                'tipo_incidencia' => 'sobrante',
                'descripcion' => 'Segunda incidencia',
            ],
            $this->admin
        );

        $servicio->cambiarEstado(
            $primera,
            'en_revision',
            $this->admin
        );

        $resultado = $servicio->listar(
            $this->admin,
            [
                'buscar' => $producto->codigo,
                'tipo_incidencia' => 'faltante',
                'estado' => 'en_revision',
            ]
        );

        $this->assertSame(1, $resultado->total());

        $this->assertEquals(
            $primera->id,
            $resultado->items()[0]->id
        );

        $resultado = $servicio->listar(
            $this->admin,
            [
                'tipo_incidencia' => 'faltante',
                'estado' => 'abierta',
            ]
        );

        $this->assertSame(0, $resultado->total());
    }

    public function test_c58_permisos_del_encargado_en_inventario(): void
    {
        $rol = \Illuminate\Support\Facades\DB::table('roles')
            ->where('slug', 'encargado')
            ->first();

        $rolId = $rol?->id;

        if ($rolId === null) {
            $rolId = \Illuminate\Support\Facades\DB::table('roles')
                ->insertGetId([
                    'nombre' => 'Encargado',
                    'slug' => 'encargado',
                    'estado' => 'activo',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
        }

        $permisos = \Illuminate\Support\Facades\DB::table('permisos')
            ->whereIn('clave', [
                'inventario.ver',
                'incidencias.ver',
                'incidencias.reportar',
            ])
            ->pluck('id');

        foreach ($permisos as $permisoId) {
            \Illuminate\Support\Facades\DB::table('rol_permiso')
                ->insertOrIgnore([
                    'rol_id' => $rolId,
                    'permiso_id' => $permisoId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
        }

        $encargado = \App\Models\User::factory()->create([
            'role_id' => $rolId,
            'estado' => 'activo',
        ]);

        \Illuminate\Support\Facades\DB::table('empleado_sucursal')
            ->insert([
                'usuario_id' => $encargado->id,
                'sucursal_id' => $this->sucursalId,
                'es_principal' => true,
                'fecha_inicio' => now('America/Mexico_City')
                    ->subDay()->toDateString(),
                'fecha_fin' => null,
                'estado' => 'activo',
                'asignado_por' => $this->admin->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        $producto = $this->producto('por_cantidad');

        $this->inventario->registrarEntrada(
            $producto, '10', $this->admin
        );

        $resultado = $this->inventario->listarProductos(
            $encargado
        );

        $this->assertSame(1, $resultado->total());

        $servicio = app(
            \App\Services\Inventario\IncidenciaService::class
        );

        $incidencia = $servicio->reportar(
            $producto,
            [
                'tipo_incidencia' => 'faltante',
                'descripcion' => 'Reporte del encargado',
            ],
            $encargado
        );

        $this->assertSame('abierta', $incidencia->estado);

        $operaciones = [
            fn () => $this->inventario->registrarEntrada(
                $producto, '1', $encargado
            ),
            fn () => $this->inventario->registrarSalida(
                $producto, '1', $encargado, 'Retiro'
            ),
            fn () => $this->inventario->ajustarExistencia(
                $producto, '1', 'positivo', $encargado, 'Ajuste'
            ),
            fn () => $servicio->cambiarEstado(
                $incidencia, 'en_revision', $encargado
            ),
        ];

        foreach ($operaciones as $operacion) {
            try {
                $operacion();

                $this->fail('Operacion no autorizada para encargado.');
            } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
                $this->assertInstanceOf(
                    \Illuminate\Auth\Access\AuthorizationException::class,
                    $e
                );
            }
        }

        $this->assertSame('10.0000', $this->stock($producto));

        $this->assertSame(
            'abierta',
            $incidencia->fresh()->estado
        );
    }

    public function test_c59_validacion_http_de_filtros_y_salidas(): void
    {
        $producto = $this->producto('por_cantidad');

        $this->inventario->registrarEntrada(
            $producto, '10', $this->admin
        );

        $this->actingAs($this->admin);

        $this->getJson(route('inventario.index', [
            'nivel_stock' => 'invalido',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('nivel_stock');

        $this->getJson(route('inventario.movimientos', [
            'fecha_desde' => '2026-11-20',
            'fecha_hasta' => '2026-11-19',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('fecha_hasta');

        $this->postJson(
            route('inventario.salidas.store'),
            [
                'producto_id' => $producto->id,
                'cantidad' => '2',
                'observaciones' => 'Solicitud incompleta',
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tipo_salida');

        $this->assertSame('10.0000', $this->stock($producto));

        $this->post(
            route('inventario.salidas.store'),
            [
                'producto_id' => $producto->id,
                'tipo_salida' => 'administrativa',
                'cantidad' => '2',
                'observaciones' => 'Salida administrativa HTTP',
            ]
        )
            ->assertRedirect(route('inventario.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame('8.0000', $this->stock($producto));

        $this->assertDatabaseHas('movimientos_inventario', [
            'producto_id' => $producto->id,
            'tipo_movimiento' => 'salida',
            'estado_destino_id' => null,
            'cantidad' => '2.0000',
        ]);
    }

    public function test_c60_contencion_de_bloqueo_entre_conexiones(): void
    {
        $principal = \Illuminate\Support\Facades\DB::connection();

        $this->assertSame(
            'joysys_testing',
            $principal->getDatabaseName()
        );

        $configuracion = $principal->getConfig();

        $this->assertSame(
            'joysys_test_user',
            $configuracion['username']
        );

        $nombreConexion = 'mysql_inventario_contencion';

        config([
            "database.connections.{$nombreConexion}" => $configuracion,
        ]);

        $secundaria = \Illuminate\Support\Facades\DB::connection(
            $nombreConexion
        );

        $bloqueo = 'joysys:catalogos:' . sha1(
            $principal->getDatabaseName()
        );

        $adquiridoPrincipal = false;
        $adquiridoSecundaria = false;

        try {
            $this->assertSame(
                'joysys_testing',
                $secundaria->getDatabaseName()
            );

            $idPrincipal = $principal->selectOne(
                'SELECT CONNECTION_ID() AS id',
                [],
                false
            )->id;

            $idSecundaria = $secundaria->selectOne(
                'SELECT CONNECTION_ID() AS id',
                [],
                false
            )->id;

            $this->assertNotEquals(
                $idPrincipal,
                $idSecundaria
            );

            $resultado = $principal->selectOne(
                'SELECT GET_LOCK(?, 0) AS adquirido',
                [$bloqueo],
                false
            );

            $adquiridoPrincipal = (int) $resultado->adquirido === 1;

            $this->assertTrue($adquiridoPrincipal);

            $resultado = $secundaria->selectOne(
                'SELECT GET_LOCK(?, 0) AS adquirido',
                [$bloqueo],
                false
            );

            $adquiridoSecundaria = (int) $resultado->adquirido === 1;

            $this->assertSame(
                0,
                (int) $resultado->adquirido
            );

            $resultado = $principal->selectOne(
                'SELECT RELEASE_LOCK(?) AS liberado',
                [$bloqueo],
                false
            );

            $adquiridoPrincipal = (int) $resultado->liberado !== 1;

            $this->assertSame(
                1,
                (int) $resultado->liberado
            );

            $resultado = $secundaria->selectOne(
                'SELECT GET_LOCK(?, 0) AS adquirido',
                [$bloqueo],
                false
            );

            $adquiridoSecundaria = (int) $resultado->adquirido === 1;

            $this->assertTrue($adquiridoSecundaria);
        } finally {
            if ($adquiridoPrincipal) {
                $principal->selectOne(
                    'SELECT RELEASE_LOCK(?)',
                    [$bloqueo],
                    false
                );
            }

            if ($adquiridoSecundaria) {
                $secundaria->selectOne(
                    'SELECT RELEASE_LOCK(?)',
                    [$bloqueo],
                    false
                );
            }

            \Illuminate\Support\Facades\DB::purge(
                $nombreConexion
            );

            config([
                "database.connections.{$nombreConexion}" => null,
            ]);
        }

        $this->assertSame(
            1,
            (int) $principal->selectOne(
                'SELECT IS_FREE_LOCK(?) AS libre',
                [$bloqueo],
                false
            )->libre
        );
    }

    public function test_c61_boton_submit_deshabilitado_conserva_su_tipo(): void
    {
        $html = \Illuminate\Support\Facades\Blade::render(
            '<x-ui.button type="submit" :disabled="true">Guardar</x-ui.button>',
            [],
            true
        );

        $this->assertMatchesRegularExpression(
            '/<button\b[^>]*\btype="submit"/s',
            $html
        );

        $this->assertMatchesRegularExpression(
            '/<button\b[^>]*\sdisabled(?:\s|=|>)/s',
            $html
        );
    }

    public function test_c62_boton_submit_habilitado_conserva_su_tipo(): void
    {
        $html = \Illuminate\Support\Facades\Blade::render(
            '<x-ui.button type="submit">Guardar</x-ui.button>',
            [],
            true
        );

        $this->assertMatchesRegularExpression(
            '/<button\b[^>]*\btype="submit"/s',
            $html
        );

        $this->assertDoesNotMatchRegularExpression(
            '/<button\b[^>]*\sdisabled(?:\s|=|>)/s',
            $html
        );
    }

    public function test_c63_boton_ordinario_no_se_convierte_en_submit(): void
    {
        $html = \Illuminate\Support\Facades\Blade::render(
            '<x-ui.button type="button" :disabled="true">Cancelar</x-ui.button>',
            [],
            true
        );

        $this->assertMatchesRegularExpression(
            '/<button\b[^>]*\btype="button"/s',
            $html
        );

        $this->assertDoesNotMatchRegularExpression(
            '/<button\b[^>]*\btype="submit"/s',
            $html
        );
    }

    public function test_c64_inventario_muestra_sucursal_operativa(): void
    {
        $sucursal = \Illuminate\Support\Facades\DB::table('sucursales')
            ->where('id', $this->sucursalId)
            ->first();

        $this->assertNotNull($sucursal);

        $this->actingAs($this->admin)
            ->get(route('inventario.index'))
            ->assertOk()
            ->assertSee($sucursal->nombre)
            ->assertSee($sucursal->codigo)
            ->assertDontSee('Sin sucursal operativa')
            ->assertDontSee('Inventario — Sucursal requerida')
            ->assertDontSee('Incidencias — Sucursal requerida');
    }

    public function test_c65_incidencias_muestra_sucursal_operativa(): void
    {
        $sucursal = \Illuminate\Support\Facades\DB::table('sucursales')
            ->where('id', $this->sucursalId)
            ->first();

        $this->assertNotNull($sucursal);

        $this->actingAs($this->admin)
            ->get(route('incidencias.index'))
            ->assertOk()
            ->assertSee($sucursal->nombre)
            ->assertSee($sucursal->codigo)
            ->assertDontSee('Sin sucursal operativa');
    }

    private function concederPermisoDashboardRegresion(
        \App\Models\User $usuario
    ): void {
        $permisoId = \Illuminate\Support\Facades\DB::table('permisos')
            ->where('clave', 'dashboard.ver')
            ->value('id');

        if ($permisoId === null) {
            $permisoId = \Illuminate\Support\Facades\DB::table('permisos')
                ->insertGetId([
                    'clave' => 'dashboard.ver',
                    'nombre' => 'Consultar dashboard',
                    'modulo' => 'dashboard',
                    'descripcion' => 'Permiso temporal de regresion',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
        }

        \Illuminate\Support\Facades\DB::table('rol_permiso')
            ->insertOrIgnore([
                'rol_id' => $usuario->role_id,
                'permiso_id' => $permisoId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        $this->assertTrue(
            $usuario->can('joysys.permiso', 'dashboard.ver')
        );
    }

    public function test_c66_layout_resuelve_sucursal_sin_middleware_obligatorio(): void
    {
        $this->concederPermisoDashboardRegresion($this->admin);

        $sucursal = \Illuminate\Support\Facades\DB::table('sucursales')
            ->where('id', $this->sucursalId)
            ->first();

        $this->assertNotNull($sucursal);

        $ruta = app('router')
            ->getRoutes()
            ->getByName('dashboard');

        $this->assertNotNull($ruta);

        $this->assertNotContains(
            'sucursal.activa',
            $ruta->gatherMiddleware()
        );

        $respuesta = $this->actingAs($this->admin)
            ->get(route('dashboard'))
            ->assertOk();

        $html = $respuesta->getContent();

        $this->assertSame(
            1,
            preg_match(
                '/<header\b[^>]*>.*?<\/header>/s',
                $html,
                $cabecera
            )
        );

        $this->assertStringContainsString(
            e($sucursal->nombre),
            $cabecera[0]
        );

        $this->assertStringContainsString(
            e($sucursal->codigo),
            $cabecera[0]
        );

        $this->assertStringNotContainsString(
            'Sin sucursal operativa',
            $cabecera[0]
        );

        $respuesta
            ->assertDontSee('Inventario — Sucursal requerida')
            ->assertDontSee('Incidencias — Sucursal requerida');
    }

    public function test_c67_usuario_sin_sucursal_conserva_restricciones(): void
    {
        $empleado = $this->crearEmpleadoInventario(false);

        $this->concederPermisoDashboardRegresion($empleado);

        $respuesta = $this->actingAs($empleado)
            ->get(route('dashboard'))
            ->assertOk();

        $html = $respuesta->getContent();

        $this->assertSame(
            1,
            preg_match(
                '/<header\b[^>]*>.*?<\/header>/s',
                $html,
                $cabecera
            )
        );

        $this->assertStringContainsString(
            'Sin sucursal operativa',
            $cabecera[0]
        );

        $this->assertStringContainsString(
            'No asignado',
            $cabecera[0]
        );

        $respuesta
            ->assertSee('Inventario — Sucursal requerida')
            ->assertSee('Incidencias — Sucursal requerida');

        $this->get(route('inventario.index'))
            ->assertForbidden();

        $this->get(route('incidencias.index'))
            ->assertForbidden();
    }

}
