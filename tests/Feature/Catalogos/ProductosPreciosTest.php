<?php

namespace Tests\Feature\Catalogos;

use App\Models\Categoria;
use App\Models\Metal;
use App\Models\Pureza;
use App\Models\Proveedor;
use App\Models\Producto;
use App\Models\PrecioProducto;
use App\Models\HistorialPrecio;
use App\Models\User;
use App\Services\Catalogos\ProductoService;
use App\Services\Precios\PrecioService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\TestCase;

class ProductosPreciosTest extends TestCase
{
    private User $admin;
    private User $encargado;
    private User $empleado;

    private Categoria $categoria;
    private Metal $metal;
    private Pureza $pureza;

    private ProductoService $productos;
    private PrecioService $precios;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame(
            'joysys_testing',
            DB::connection()->getDatabaseName()
        );

        $permisos = [
            'dashboard.ver',
            'catalogos.ver',
            'catalogos.crear',
            'catalogos.editar',
            'catalogos.cambiar_estado',
            'precios.ver',
            'precios.modificar',
            'precios.ver_historial',
        ];

        $ids = [];

        foreach ($permisos as $clave) {
            $ids[$clave] = DB::table('permisos')->insertGetId([
                'clave' => $clave,
                'nombre' => $clave,
                'modulo' => explode('.', $clave)[0],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->admin = $this->crearUsuario(
            'administrador',
            $ids,
            $permisos
        );

        $lectura = [
            'dashboard.ver',
            'catalogos.ver',
            'precios.ver',
        ];

        $this->encargado = $this->crearUsuario(
            'encargado',
            $ids,
            $lectura
        );

        $this->empleado = $this->crearUsuario(
            'empleado',
            $ids,
            $lectura
        );

        $this->categoria = Categoria::create([
            'nombre' => 'Anillos de prueba',
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

        $this->productos = app(ProductoService::class);
        $this->precios = app(PrecioService::class);
    }

    private function crearUsuario(
        string $rol,
        array $ids,
        array $permisos
    ): User {
        $rolId = DB::table('roles')->insertGetId([
            'nombre' => ucfirst($rol),
            'slug' => $rol,
            'estado' => 'activo',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($permisos as $clave) {
            DB::table('rol_permiso')->insert([
                'rol_id' => $rolId,
                'permiso_id' => $ids[$clave],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return User::factory()->create([
            'role_id' => $rolId,
        ]);
    }

    private function datos(
        array $cambios = []
    ): array {
        return array_replace([
            'categoria_id' => $this->categoria->id,
            'proveedor_id' => null,
            'metal_id' => $this->metal->id,
            'pureza_id' => $this->pureza->id,
            'codigo' => 'PRUEBA-001',
            'nombre' => 'Anillo de prueba',
            'descripcion' => 'Registro de pruebas automatizadas.',
            'tipo' => 'individual',
            'precio_base' => '500.0000',
            'stock_minimo' => '0.0000',
        ], $cambios);
    }

    private function crearProducto(
        array $cambios = []
    ): Producto {
        return $this->productos->crear(
            $this->datos($cambios),
            $this->admin
        );
    }

    // ================================================
    // PRODUCTOS
    // ================================================

    public function test_crea_producto_precio_e_historial(): void
    {
        $producto = $this->crearProducto();

        $this->assertDatabaseHas('productos', [
            'id' => $producto->id,
            'codigo' => 'PRUEBA-001',
            'estado' => 'activo',
            'precio_base' => '500.0000',
        ]);

        $this->assertDatabaseHas('precios_productos', [
            'producto_id' => $producto->id,
            'precio' => '500.0000',
            'moneda' => 'MXN',
        ]);

        $this->assertDatabaseHas('historial_precios', [
            'producto_id' => $producto->id,
            'precio_anterior' => null,
            'precio_nuevo' => '500.0000',
            'usuario_id' => $this->admin->id,
            'moneda' => 'MXN',
        ]);

        $this->assertSame(1, $producto->historialPrecios()->count());
    }

    public function test_permite_catalogos_opcionales_nulos(): void
    {
        $producto = $this->crearProducto([
            'metal_id' => null,
            'pureza_id' => null,
            'proveedor_id' => null,
        ]);

        $this->assertNull($producto->metal_id);
        $this->assertNull($producto->pureza_id);
        $this->assertNull($producto->proveedor_id);
    }

    public function test_permite_los_tres_tipos_aprobados(): void
    {
        foreach (ProductoService::TIPOS as $i => $tipo) {
            $producto = $this->crearProducto([
                'codigo' => 'TIPO-' . $i,
                'tipo' => $tipo,
            ]);

            $this->assertSame($tipo, $producto->tipo);
        }

        $this->assertSame(3, Producto::count());
        $this->assertSame(3, PrecioProducto::count());
        $this->assertSame(3, HistorialPrecio::count());
    }

    public function test_rechaza_tipo_invalido(): void
    {
        $this->expectException(ValidationException::class);

        $this->crearProducto([
            'tipo' => 'cantidad',
        ]);
    }

    public function test_rechaza_codigo_duplicado(): void
    {
        $this->crearProducto();

        $this->expectException(ValidationException::class);

        $this->crearProducto();
    }

    public function test_no_reutiliza_codigo_eliminado_logicamente(): void
    {
        $producto = $this->crearProducto();
        $producto->delete();

        $this->expectException(ValidationException::class);

        $this->crearProducto();
    }

    public function test_rechaza_categoria_inactiva(): void
    {
        $this->categoria->update([
            'estado' => 'inactiva',
        ]);

        $this->expectException(ValidationException::class);

        $this->crearProducto();
    }

    public function test_rechaza_categoria_con_ancestro_inactivo(): void
    {
        $padre = Categoria::create([
            'nombre' => 'Padre inactivo',
            'estado' => 'inactiva',
        ]);

        $this->categoria->update([
            'categoria_padre_id' => $padre->id,
        ]);

        $this->expectException(ValidationException::class);

        $this->crearProducto();
    }

    public function test_rechaza_metal_inactivo(): void
    {
        $this->metal->update([
            'estado' => 'inactivo',
        ]);

        $this->expectException(ValidationException::class);

        $this->crearProducto();
    }

    public function test_rechaza_pureza_de_otro_metal(): void
    {
        $otro = Metal::create([
            'nombre' => 'Plata de prueba',
            'estado' => 'activo',
        ]);

        $this->expectException(ValidationException::class);

        $this->crearProducto([
            'metal_id' => $otro->id,
        ]);
    }

    public function test_rechaza_pureza_sin_metal(): void
    {
        $this->expectException(ValidationException::class);

        $this->crearProducto([
            'metal_id' => null,
        ]);
    }

    public function test_permite_proveedor_activo(): void
    {
        $proveedor = Proveedor::create([
            'nombre_comercial' => 'Proveedor de prueba',
            'estado' => 'activo',
        ]);

        $producto = $this->crearProducto([
            'proveedor_id' => $proveedor->id,
        ]);

        $this->assertEquals(
            $proveedor->id,
            $producto->proveedor_id
        );
    }

    public function test_rechaza_proveedor_inactivo(): void
    {
        $proveedor = Proveedor::create([
            'nombre_comercial' => 'Proveedor inactivo',
            'estado' => 'inactivo',
        ]);

        $this->expectException(ValidationException::class);

        $this->crearProducto([
            'proveedor_id' => $proveedor->id,
        ]);
    }

    public function test_edita_producto_sin_modificar_precio_base(): void
    {
        $producto = $this->crearProducto();

        $actualizado = $this->productos->actualizar(
            $producto,
            $this->datos([
                'nombre' => 'Anillo actualizado',
                'precio_base' => '900.0000',
            ]),
            $this->admin
        );

        $this->assertSame(
            'Anillo actualizado',
            $actualizado->nombre
        );

        $this->assertSame(
            '500.0000',
            $actualizado->precio_base
        );

        $this->assertSame(
            '500.0000',
            $actualizado->precioVigente->precio
        );

        $this->assertSame(
            1,
            $actualizado->historialPrecios()->count()
        );
    }

    public function test_desactiva_y_reactiva_producto_sin_inventario(): void
    {
        $producto = $this->crearProducto();

        $producto = $this->productos->cambiarEstado(
            $producto,
            'inactivo',
            $this->admin
        );

        $this->assertSame('inactivo', $producto->estado);

        $producto = $this->productos->cambiarEstado(
            $producto,
            'activo',
            $this->admin
        );

        $this->assertSame('activo', $producto->estado);
    }

    public function test_rechaza_reactivacion_con_categoria_inactiva(): void
    {
        $producto = $this->crearProducto();

        $this->productos->cambiarEstado(
            $producto,
            'inactivo',
            $this->admin
        );

        $this->categoria->update([
            'estado' => 'inactiva',
        ]);

        $this->expectException(ValidationException::class);

        $this->productos->cambiarEstado(
            $producto,
            'activo',
            $this->admin
        );
    }

    public function test_busqueda_interpreta_comodines_como_literales(): void
    {
        $this->crearProducto();

        $this->assertSame(
            0,
            $this->productos->listar(['buscar' => '%'])->total()
        );

        $this->assertSame(
            0,
            $this->productos->listar(['buscar' => '_'])->total()
        );

        $this->assertSame(
            1,
            $this->productos->listar(['buscar' => 'PRUEBA'])->total()
        );
    }

    // ================================================
    // PRECIOS E HISTORIAL
    // ================================================

    public function test_actualiza_precio_y_conserva_historial(): void
    {
        $producto = $this->crearProducto();

        $this->precios->actualizar(
            $producto,
            '650.1234',
            $this->admin
        );

        $this->assertSame(
            '500.0000',
            $producto->refresh()->precio_base
        );

        $this->assertSame(
            '650.1234',
            $producto->precioVigente->precio
        );

        $this->assertSame(1, PrecioProducto::count());
        $this->assertSame(2, HistorialPrecio::count());

        $this->assertDatabaseHas('historial_precios', [
            'producto_id' => $producto->id,
            'precio_anterior' => '500.0000',
            'precio_nuevo' => '650.1234',
            'usuario_id' => $this->admin->id,
        ]);
    }

    public function test_precio_identico_no_genera_historial(): void
    {
        $producto = $this->crearProducto();

        $fecha = $producto->precioVigente
            ->vigente_desde
            ->toDateTimeString();

        $this->precios->actualizar(
            $producto,
            '500',
            $this->admin
        );

        $this->assertSame(1, HistorialPrecio::count());

        $this->assertSame(
            $fecha,
            $producto->refresh()
                ->precioVigente
                ->vigente_desde
                ->toDateTimeString()
        );
    }

    public function test_conserva_cadena_de_precios(): void
    {
        $producto = $this->crearProducto();

        $this->precios->actualizar(
            $producto,
            '650',
            $this->admin
        );

        $this->precios->actualizar(
            $producto,
            '700',
            $this->admin
        );

        $historial = $producto->historialPrecios()
            ->orderBy('id')
            ->get();

        $this->assertCount(3, $historial);
        $this->assertNull($historial[0]->precio_anterior);
        $this->assertSame('500.0000', $historial[0]->precio_nuevo);
        $this->assertSame('500.0000', $historial[1]->precio_anterior);
        $this->assertSame('650.0000', $historial[1]->precio_nuevo);
        $this->assertSame('650.0000', $historial[2]->precio_anterior);
        $this->assertSame('700.0000', $historial[2]->precio_nuevo);
    }

    public function test_rechaza_importes_invalidos(): void
    {
        foreach ([
            '0',
            '-1',
            '1.12345',
            '100000000',
            '1e3',
            'abc',
            '1,500',
            '',
        ] as $importe) {
            try {
                $this->precios->normalizarImporte($importe);

                $this->fail(
                    'Se acepto un importe invalido: ' . $importe
                );
            } catch (ValidationException $e) {
                $this->assertArrayHasKey(
                    'precio',
                    $e->errors()
                );
            }
        }
    }

    public function test_normaliza_importes_sin_perder_precision(): void
    {
        $this->assertSame(
            '0.0001',
            $this->precios->normalizarImporte('0.0001')
        );

        $this->assertSame(
            '99999999.9999',
            $this->precios->normalizarImporte('99999999.9999')
        );

        $this->assertSame(
            '500.1000',
            $this->precios->normalizarImporte('000500.1')
        );
    }

    public function test_precio_inicial_invalido_no_crea_registros(): void
    {
        try {
            $this->crearProducto([
                'precio_base' => '0',
            ]);

            $this->fail('Se acepto un precio inicial invalido.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey(
                'precio_base',
                $e->errors()
            );
        }

        $this->assertSame(0, Producto::count());
        $this->assertSame(0, PrecioProducto::count());
        $this->assertSame(0, HistorialPrecio::count());
    }

    public function test_no_permite_inicializar_precio_dos_veces(): void
    {
        $producto = $this->crearProducto();

        $this->expectException(ValidationException::class);

        $this->precios->inicializar(
            $producto,
            '500',
            $this->admin
        );
    }

    public function test_actualiza_precio_de_producto_inactivo(): void
    {
        $producto = $this->crearProducto();

        $this->productos->cambiarEstado(
            $producto,
            'inactivo',
            $this->admin
        );

        $this->precios->actualizar(
            $producto,
            '800',
            $this->admin
        );

        $producto->refresh();

        $this->assertSame('inactivo', $producto->estado);
        $this->assertSame(
            '800.0000',
            $producto->precioVigente->precio
        );
    }

    // ================================================
    // AUTORIZACION Y VISTAS
    // ================================================

    public function test_invitado_no_accede_a_productos_ni_precios(): void
    {
        $this->get(route('catalogos.productos.index'))
            ->assertRedirect(route('login'));

        $this->get(route('precios.index'))
            ->assertRedirect(route('login'));
    }

    public function test_administrador_consulta_vistas_con_registros(): void
    {
        $producto = $this->crearProducto();

        $this->actingAs($this->admin);

        $this->get(route('catalogos.productos.index'))
            ->assertOk()
            ->assertSee('PRUEBA-001');

        $this->get(route('catalogos.productos.create'))
            ->assertOk();

        $this->get(route('catalogos.productos.edit', $producto))
            ->assertOk()
            ->assertSee('Anillo de prueba');

        $this->get(route('precios.index'))
            ->assertOk()
            ->assertSee('500.0000');

        $this->get(route('precios.edit', $producto))
            ->assertOk();

        $this->get(route('precios.historial', $producto))
            ->assertOk()
            ->assertSee('500.0000');
    }

    public function test_roles_de_consulta_no_pueden_modificar(): void
    {
        $producto = $this->crearProducto();

        foreach ([$this->encargado, $this->empleado] as $usuario) {
            $this->actingAs($usuario);

            $this->get(route('catalogos.productos.index'))
                ->assertOk()
                ->assertDontSee('Nuevo producto');

            $this->get(route('precios.index'))
                ->assertOk()
                ->assertDontSee('Modificar');

            $this->get(route('catalogos.productos.create'))
                ->assertForbidden();

            $this->post(
                route('catalogos.productos.store'),
                $this->datos()
            )->assertForbidden();

            $this->get(route('catalogos.productos.edit', $producto))
                ->assertForbidden();

            $this->put(
                route('catalogos.productos.update', $producto),
                $this->datos()
            )->assertForbidden();

            $this->patch(
                route('catalogos.productos.estado', $producto),
                ['estado' => 'inactivo']
            )->assertForbidden();

            $this->get(route('precios.edit', $producto))
                ->assertForbidden();

            $this->put(
                route('precios.update', $producto),
                ['precio' => '900']
            )->assertForbidden();

            $this->get(route('precios.historial', $producto))
                ->assertForbidden();
        }
    }

    public function test_servicio_impide_creacion_sin_permiso(): void
    {
        $this->expectException(AuthorizationException::class);

        $this->productos->crear(
            $this->datos(),
            $this->empleado
        );
    }

    public function test_servicio_impide_cambiar_precio_sin_permiso(): void
    {
        $producto = $this->crearProducto();

        $this->expectException(AuthorizationException::class);

        $this->precios->actualizar(
            $producto,
            '900',
            $this->encargado
        );
    }

    public function test_registro_http_genera_los_tres_registros(): void
    {
        $this->actingAs($this->admin)
            ->post(
                route('catalogos.productos.store'),
                $this->datos()
            )
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('catalogos.productos.index'));

        $this->assertSame(1, Producto::count());
        $this->assertSame(1, PrecioProducto::count());
        $this->assertSame(1, HistorialPrecio::count());
    }

    public function test_http_rechaza_manipulacion_del_precio_base(): void
    {
        $producto = $this->crearProducto();

        $this->actingAs($this->admin)
            ->from(route('catalogos.productos.edit', $producto))
            ->put(
                route('catalogos.productos.update', $producto),
                $this->datos([
                    'precio_base' => '1',
                ])
            )
            ->assertSessionHasErrors('precio_base');

        $this->assertSame(
            '500.0000',
            $producto->refresh()->precio_base
        );
    }

    public function test_http_rechaza_manipulacion_de_moneda(): void
    {
        $producto = $this->crearProducto();

        $this->actingAs($this->admin)
            ->from(route('precios.edit', $producto))
            ->put(
                route('precios.update', $producto),
                [
                    'precio' => '800',
                    'moneda' => 'USD',
                ]
            )
            ->assertSessionHasErrors('moneda');

        $this->assertSame(
            '500.0000',
            $producto->refresh()->precioVigente->precio
        );
    }

    public function test_http_actualiza_precio_correctamente(): void
    {
        $producto = $this->crearProducto();

        $this->actingAs($this->admin)
            ->put(
                route('precios.update', $producto),
                ['precio' => '750.1234']
            )
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('precios.index'));

        $this->assertSame(
            '750.1234',
            $producto->refresh()->precioVigente->precio
        );

        $this->assertSame(2, HistorialPrecio::count());
    }

    public function test_http_rechaza_filtros_invalidos(): void
    {
        $this->actingAs($this->admin);

        $this->get(
            route('catalogos.productos.index') . '?tipo=invalido'
        )->assertSessionHasErrors('tipo');

        $this->get(
            route('precios.index') . '?estado=invalido'
        )->assertSessionHasErrors('estado');
    }

    // ================================================
    // INTEGRIDAD REFERENCIAL Y TRANSACCIONAL
    // ================================================

    private function crearSucursalIntegridad(): int
    {
        return DB::table('sucursales')->insertGetId([
            'codigo' => 'SUC-INTEGRIDAD',
            'nombre' => 'Sucursal de pruebas',
            'estado' => 'activa',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function crearEstadoIntegridad(
        string $codigo
    ): int {
        return DB::table('estados_inventario')->insertGetId([
            'codigo' => $codigo,
            'nombre' => ucfirst($codigo),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function crearPiezaIntegridad(
        Producto $producto,
        string $estado = 'disponible'
    ): int {
        return DB::table('piezas')->insertGetId([
            'producto_id' => $producto->id,
            'sucursal_id' => $this->crearSucursalIntegridad(),
            'estado_inventario_id' => $this->crearEstadoIntegridad($estado),
            'metal_id' => $producto->metal_id,
            'pureza_id' => $producto->pureza_id,
            'codigo_pieza' => 'PIEZA-INTEGRIDAD-001',
            'peso_gramos' => '2.500',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function crearExistenciaIntegridad(
        Producto $producto,
        string $cantidad,
        string $estado = 'disponible'
    ): int {
        return DB::table('existencias')->insertGetId([
            'producto_id' => $producto->id,
            'sucursal_id' => $this->crearSucursalIntegridad(),
            'estado_inventario_id' => $this->crearEstadoIntegridad($estado),
            'cantidad' => $cantidad,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_integridad_protege_codigo_referenciado(): void
    {
        $producto = $this->crearProducto();

        $this->crearPiezaIntegridad($producto);

        try {
            $this->productos->actualizar(
                $producto,
                $this->datos([
                    'codigo' => 'CODIGO-MODIFICADO',
                ]),
                $this->admin
            );

            $this->fail('Se modifico un codigo protegido.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('codigo', $e->errors());
        }

        $this->assertSame(
            'PRUEBA-001',
            $producto->refresh()->codigo
        );
    }

    public function test_integridad_protege_tipo_referenciado(): void
    {
        $producto = $this->crearProducto();

        $this->crearPiezaIntegridad($producto);

        try {
            $this->productos->actualizar(
                $producto,
                $this->datos([
                    'tipo' => 'por_cantidad',
                ]),
                $this->admin
            );

            $this->fail('Se modifico un tipo protegido.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('tipo', $e->errors());
        }

        $this->assertSame(
            'individual',
            $producto->refresh()->tipo
        );
    }

    public function test_integridad_protege_metal_referenciado(): void
    {
        $producto = $this->crearProducto();

        $this->crearPiezaIntegridad($producto);

        $otroMetal = Metal::create([
            'nombre' => 'Metal alternativo',
            'estado' => 'activo',
        ]);

        try {
            $this->productos->actualizar(
                $producto,
                $this->datos([
                    'metal_id' => $otroMetal->id,
                    'pureza_id' => null,
                ]),
                $this->admin
            );

            $this->fail('Se modifico un metal protegido.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('metal_id', $e->errors());
        }

        $this->assertEquals(
            $this->metal->id,
            $producto->refresh()->metal_id
        );
    }

    public function test_integridad_protege_pureza_referenciada(): void
    {
        $producto = $this->crearProducto();

        $this->crearPiezaIntegridad($producto);

        $otraPureza = Pureza::create([
            'metal_id' => $this->metal->id,
            'codigo' => '14K',
            'nombre' => 'Oro 14 quilates',
        ]);

        try {
            $this->productos->actualizar(
                $producto,
                $this->datos([
                    'pureza_id' => $otraPureza->id,
                ]),
                $this->admin
            );

            $this->fail('Se modifico una pureza protegida.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('pureza_id', $e->errors());
        }

        $this->assertEquals(
            $this->pureza->id,
            $producto->refresh()->pureza_id
        );
    }

    public function test_integridad_permite_editar_nombre_referenciado(): void
    {
        $producto = $this->crearProducto();

        $this->crearPiezaIntegridad($producto);

        $actualizado = $this->productos->actualizar(
            $producto,
            $this->datos([
                'nombre' => 'Nombre actualizado',
            ]),
            $this->admin
        );

        $this->assertSame(
            'Nombre actualizado',
            $actualizado->nombre
        );

        $this->assertSame(
            'PRUEBA-001',
            $actualizado->codigo
        );
    }

    public function test_integridad_bloquea_desactivacion_con_piezas(): void
    {
        $producto = $this->crearProducto();

        $this->crearPiezaIntegridad($producto);

        try {
            $this->productos->cambiarEstado(
                $producto,
                'inactivo',
                $this->admin
            );

            $this->fail('Se desactivo un producto con piezas disponibles.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('estado', $e->errors());
        }

        $this->assertSame(
            'activo',
            $producto->refresh()->estado
        );
    }

    public function test_integridad_bloquea_desactivacion_con_existencias(): void
    {
        $producto = $this->crearProducto([
            'tipo' => 'por_cantidad',
        ]);

        $this->crearExistenciaIntegridad(
            $producto,
            '5.0000'
        );

        try {
            $this->productos->cambiarEstado(
                $producto,
                'inactivo',
                $this->admin
            );

            $this->fail('Se desactivo un producto con existencias.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('estado', $e->errors());
        }

        $this->assertSame(
            'activo',
            $producto->refresh()->estado
        );
    }

    public function test_integridad_permite_desactivar_existencia_cero(): void
    {
        $producto = $this->crearProducto([
            'tipo' => 'por_cantidad',
        ]);

        $this->crearExistenciaIntegridad(
            $producto,
            '0.0000'
        );

        $actualizado = $this->productos->cambiarEstado(
            $producto,
            'inactivo',
            $this->admin
        );

        $this->assertSame(
            'inactivo',
            $actualizado->estado
        );

        $this->assertTrue(
            $this->productos->tieneReferencias($actualizado)
        );
    }

    public function test_integridad_permite_desactivar_piezas_vendidas(): void
    {
        $producto = $this->crearProducto();

        $this->crearPiezaIntegridad(
            $producto,
            'vendido'
        );

        $actualizado = $this->productos->cambiarEstado(
            $producto,
            'inactivo',
            $this->admin
        );

        $this->assertSame(
            'inactivo',
            $actualizado->estado
        );
    }

    public function test_integridad_bloquea_piezas_en_reparacion(): void
    {
        $producto = $this->crearProducto();

        $this->crearPiezaIntegridad(
            $producto,
            'reparacion'
        );

        $this->expectException(ValidationException::class);

        $this->productos->cambiarEstado(
            $producto,
            'inactivo',
            $this->admin
        );
    }

    public function test_integridad_bloquea_piezas_apartadas(): void
    {
        $producto = $this->crearProducto();

        $this->crearPiezaIntegridad(
            $producto,
            'apartado'
        );

        $this->expectException(ValidationException::class);

        $this->productos->cambiarEstado(
            $producto,
            'inactivo',
            $this->admin
        );
    }

    public function test_integridad_revierte_creacion_si_falla_historial(): void
    {
        $dispatcherOriginal = HistorialPrecio::getEventDispatcher();

        $dispatcherPrueba = clone $dispatcherOriginal;

        HistorialPrecio::setEventDispatcher($dispatcherPrueba);

        HistorialPrecio::creating(function () {
            throw new \RuntimeException('FALLO-HISTORIAL-PRUEBA');
        });

        try {
            $this->crearProducto();

            $this->fail('No se produjo el fallo simulado.');
        } catch (\RuntimeException $e) {
            $this->assertSame(
                'FALLO-HISTORIAL-PRUEBA',
                $e->getMessage()
            );
        } finally {
            HistorialPrecio::setEventDispatcher($dispatcherOriginal);
        }

        $this->assertSame(0, Producto::count());
        $this->assertSame(0, PrecioProducto::count());
        $this->assertSame(0, HistorialPrecio::count());

        $producto = $this->crearProducto();

        $this->assertSame(
            '500.0000',
            $producto->precioVigente->precio
        );
    }

    public function test_integridad_revierte_precio_si_falla_historial(): void
    {
        $producto = $this->crearProducto();

        $fechaOriginal = $producto->precioVigente
            ->vigente_desde
            ->toDateTimeString();

        $dispatcherOriginal = HistorialPrecio::getEventDispatcher();

        $dispatcherPrueba = clone $dispatcherOriginal;

        HistorialPrecio::setEventDispatcher($dispatcherPrueba);

        HistorialPrecio::creating(function () {
            throw new \RuntimeException('FALLO-PRECIO-PRUEBA');
        });

        try {
            $this->precios->actualizar(
                $producto,
                '999.0000',
                $this->admin
            );

            $this->fail('No se produjo el fallo simulado.');
        } catch (\RuntimeException $e) {
            $this->assertSame(
                'FALLO-PRECIO-PRUEBA',
                $e->getMessage()
            );
        } finally {
            HistorialPrecio::setEventDispatcher($dispatcherOriginal);
        }

        $producto->refresh();

        $this->assertSame(
            '500.0000',
            $producto->precioVigente->precio
        );

        $this->assertSame(
            $fechaOriginal,
            $producto->precioVigente
                ->vigente_desde
                ->toDateTimeString()
        );

        $this->assertSame(1, HistorialPrecio::count());

        $this->precios->actualizar(
            $producto,
            '600',
            $this->admin
        );

        $this->assertSame(
            '600.0000',
            $producto->refresh()->precioVigente->precio
        );
    }

    public function test_integridad_mysql_impide_precio_vigente_duplicado(): void
    {
        $producto = $this->crearProducto();

        $this->expectException(
            \Illuminate\Database\QueryException::class
        );

        PrecioProducto::create([
            'producto_id' => $producto->id,
            'precio' => '700.0000',
            'moneda' => 'MXN',
            'vigente_desde' => now(),
        ]);
    }

    public function test_integridad_mysql_rechaza_precio_cero(): void
    {
        $producto = $this->crearProducto();

        $this->expectException(
            \Illuminate\Database\QueryException::class
        );

        DB::table('precios_productos')
            ->where('producto_id', $producto->id)
            ->update([
                'precio' => '0.0000',
            ]);
    }

    public function test_integridad_mysql_rechaza_historial_negativo(): void
    {
        $producto = $this->crearProducto();

        $this->expectException(
            \Illuminate\Database\QueryException::class
        );

        HistorialPrecio::create([
            'producto_id' => $producto->id,
            'precio_anterior' => '500.0000',
            'precio_nuevo' => '-1.0000',
            'moneda' => 'MXN',
            'vigente_desde' => now(),
            'usuario_id' => $this->admin->id,
        ]);
    }

    public function test_integridad_no_existen_rutas_de_eliminacion(): void
    {
        $rutas = app('router')->getRoutes();

        foreach ($rutas as $ruta) {
            $nombre = $ruta->getName() ?? '';

            if (
                str_starts_with($nombre, 'catalogos.productos.') ||
                str_starts_with($nombre, 'precios.')
            ) {
                $this->assertNotContains(
                    'DELETE',
                    $ruta->methods()
                );
            }
        }

        $this->assertFalse(
            $rutas->hasNamedRoute('precios.historial.update')
        );

        $this->assertFalse(
            $rutas->hasNamedRoute('precios.historial.destroy')
        );
    }

    public function test_integridad_http_rechaza_identidad_manipulada(): void
    {
        $producto = $this->crearProducto();

        $this->crearPiezaIntegridad($producto);

        $datos = $this->datos([
            'codigo' => 'CODIGO-ALTERADO',
        ]);

        unset($datos['precio_base']);

        $this->actingAs($this->admin)
            ->from(route('catalogos.productos.edit', $producto))
            ->put(
                route('catalogos.productos.update', $producto),
                $datos
            )
            ->assertSessionHasErrors('codigo');

        $this->assertSame(
            'PRUEBA-001',
            $producto->refresh()->codigo
        );
    }

    public function test_integridad_http_rechaza_usuario_de_precio_manipulado(): void
    {
        $producto = $this->crearProducto();

        $this->actingAs($this->admin)
            ->from(route('precios.edit', $producto))
            ->put(
                route('precios.update', $producto),
                [
                    'precio' => '800',
                    'usuario_id' => $this->empleado->id,
                ]
            )
            ->assertSessionHasErrors('usuario_id');

        $this->assertSame(1, HistorialPrecio::count());

        $this->assertSame(
            '500.0000',
            $producto->refresh()->precioVigente->precio
        );
    }

    public function test_integridad_http_rechaza_precio_con_exceso_decimales(): void
    {
        $producto = $this->crearProducto();

        $this->actingAs($this->admin)
            ->from(route('precios.edit', $producto))
            ->put(
                route('precios.update', $producto),
                ['precio' => '500.12345']
            )
            ->assertSessionHasErrors('precio');

        $this->assertSame(1, HistorialPrecio::count());
    }

    // ================================================
    // VALIDACION FINAL DE CORRECCIONES TECNICAS
    // ================================================

    public function test_correccion_conserva_categoria_inactiva(): void
    {
        $producto = $this->crearProducto();

        $this->productos->cambiarEstado(
            $producto,
            'inactivo',
            $this->admin
        );

        $this->categoria->update([
            'estado' => 'inactiva',
        ]);

        $actualizado = $this->productos->actualizar(
            $producto,
            $this->datos([
                'nombre' => 'Producto historico actualizado',
            ]),
            $this->admin
        );

        $this->assertSame(
            'Producto historico actualizado',
            $actualizado->nombre
        );

        $this->assertEquals(
            $this->categoria->id,
            $actualizado->categoria_id
        );

        $this->assertSame('inactivo', $actualizado->estado);
    }

    public function test_correccion_conserva_metal_y_pureza_inactivos(): void
    {
        $producto = $this->crearProducto();

        $this->crearPiezaIntegridad($producto, 'vendido');

        $this->productos->cambiarEstado(
            $producto,
            'inactivo',
            $this->admin
        );

        $this->metal->update([
            'estado' => 'inactivo',
        ]);

        $actualizado = $this->productos->actualizar(
            $producto,
            $this->datos([
                'nombre' => 'Producto vendido actualizado',
            ]),
            $this->admin
        );

        $this->assertSame(
            'Producto vendido actualizado',
            $actualizado->nombre
        );

        $this->assertEquals(
            $this->metal->id,
            $actualizado->metal_id
        );

        $this->assertEquals(
            $this->pureza->id,
            $actualizado->pureza_id
        );
    }

    public function test_correccion_conserva_proveedor_inactivo(): void
    {
        $proveedor = Proveedor::create([
            'nombre_comercial' => 'Proveedor historico',
            'estado' => 'activo',
        ]);

        $producto = $this->crearProducto([
            'proveedor_id' => $proveedor->id,
        ]);

        $this->productos->cambiarEstado(
            $producto,
            'inactivo',
            $this->admin
        );

        $proveedor->update([
            'estado' => 'inactivo',
        ]);

        $actualizado = $this->productos->actualizar(
            $producto,
            $this->datos([
                'proveedor_id' => $proveedor->id,
                'nombre' => 'Producto actualizado',
            ]),
            $this->admin
        );

        $this->assertEquals(
            $proveedor->id,
            $actualizado->proveedor_id
        );

        $this->assertSame(
            'Producto actualizado',
            $actualizado->nombre
        );
    }

    public function test_correccion_rechaza_nueva_categoria_inactiva(): void
    {
        $producto = $this->crearProducto();

        $this->productos->cambiarEstado(
            $producto,
            'inactivo',
            $this->admin
        );

        $otraCategoria = Categoria::create([
            'nombre' => 'Categoria no disponible',
            'estado' => 'inactiva',
        ]);

        try {
            $this->productos->actualizar(
                $producto,
                $this->datos([
                    'categoria_id' => $otraCategoria->id,
                ]),
                $this->admin
            );

            $this->fail('Se acepto una nueva categoria inactiva.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey(
                'categoria_id',
                $e->errors()
            );
        }

        $this->assertEquals(
            $this->categoria->id,
            $producto->refresh()->categoria_id
        );
    }

    public function test_correccion_rechaza_nueva_pureza_de_metal_inactivo(): void
    {
        $producto = $this->crearProducto();

        $this->productos->cambiarEstado(
            $producto,
            'inactivo',
            $this->admin
        );

        $otraPureza = Pureza::create([
            'metal_id' => $this->metal->id,
            'codigo' => '18K',
            'nombre' => 'Oro 18 quilates',
        ]);

        $this->metal->update([
            'estado' => 'inactivo',
        ]);

        try {
            $this->productos->actualizar(
                $producto,
                $this->datos([
                    'pureza_id' => $otraPureza->id,
                ]),
                $this->admin
            );

            $this->fail('Se acepto una pureza no disponible.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey(
                'pureza_id',
                $e->errors()
            );
        }

        $this->assertEquals(
            $this->pureza->id,
            $producto->refresh()->pureza_id
        );
    }

    public function test_correccion_rechaza_reactivar_con_metal_inactivo(): void
    {
        $producto = $this->crearProducto();

        $this->productos->cambiarEstado(
            $producto,
            'inactivo',
            $this->admin
        );

        $this->metal->update([
            'estado' => 'inactivo',
        ]);

        try {
            $this->productos->cambiarEstado(
                $producto,
                'activo',
                $this->admin
            );

            $this->fail('Se reactivo con un metal inactivo.');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }

        $this->assertSame(
            'inactivo',
            $producto->refresh()->estado
        );
    }

    public function test_correccion_http_conserva_referencias_inactivas(): void
    {
        $producto = $this->crearProducto();

        $this->productos->cambiarEstado(
            $producto,
            'inactivo',
            $this->admin
        );

        $this->categoria->update([
            'estado' => 'inactiva',
        ]);

        $this->metal->update([
            'estado' => 'inactivo',
        ]);

        $datos = $this->datos([
            'nombre' => 'Actualizacion historica HTTP',
        ]);

        unset($datos['precio_base']);

        $this->actingAs($this->admin)
            ->put(
                route('catalogos.productos.update', $producto),
                $datos
            )
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('catalogos.productos.index'));

        $this->assertSame(
            'Actualizacion historica HTTP',
            $producto->refresh()->nombre
        );

        $this->assertSame('inactivo', $producto->estado);

        $this->assertEquals(
            $this->categoria->id,
            $producto->categoria_id
        );
    }

    public function test_correccion_rechaza_identificadores_invalidos(): void
    {
        foreach ([
            true,
            false,
            1.5,
            '1abc',
            '1.5',
            '1e2',
            '-1',
            '0',
            '99999999999999999999999999',
            [1],
        ] as $valor) {
            try {
                $this->crearProducto([
                    'categoria_id' => $valor,
                ]);

                $this->fail('Se acepto un identificador invalido.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey(
                    'categoria_id',
                    $e->errors()
                );
            }
        }

        $this->assertSame(0, Producto::count());
    }

    public function test_correccion_categoria_conserva_padre_inactivo(): void
    {
        $padre = Categoria::create([
            'nombre' => 'Padre historico',
            'estado' => 'inactiva',
        ]);

        $hija = Categoria::create([
            'nombre' => 'Hija historica',
            'categoria_padre_id' => $padre->id,
            'estado' => 'inactiva',
        ]);

        $this->actingAs($this->admin)
            ->get(route('catalogos.categorias.edit', $hija))
            ->assertOk()
            ->assertSee('Padre historico')
            ->assertSee('referencia actual no disponible');

        $this->put(
            route('catalogos.categorias.update', $hija),
            [
                'nombre' => 'Hija actualizada',
                'categoria_padre_id' => $padre->id,
                'descripcion' => null,
            ]
        )->assertSessionHasNoErrors();

        $hija->refresh();

        $this->assertSame('Hija actualizada', $hija->nombre);

        $this->assertEquals(
            $padre->id,
            $hija->categoria_padre_id
        );

        $this->assertSame('inactiva', $hija->estado);
    }

    public function test_correccion_categoria_rechaza_nuevo_padre_inactivo(): void
    {
        $padre = Categoria::create([
            'nombre' => 'Padre no disponible',
            'estado' => 'inactiva',
        ]);

        $hija = Categoria::create([
            'nombre' => 'Categoria independiente',
            'estado' => 'inactiva',
        ]);

        $this->actingAs($this->admin)
            ->from(route('catalogos.categorias.edit', $hija))
            ->put(
                route('catalogos.categorias.update', $hija),
                [
                    'nombre' => $hija->nombre,
                    'categoria_padre_id' => $padre->id,
                    'descripcion' => null,
                ]
            )
            ->assertSessionHasErrors('categoria_padre_id');

        $this->assertNull(
            $hija->refresh()->categoria_padre_id
        );
    }

    public function test_correccion_historial_impide_actualizacion(): void
    {
        $producto = $this->crearProducto();

        $historial = $producto->historialPrecios()->firstOrFail();

        try {
            $historial->update([
                'precio_nuevo' => '1.0000',
            ]);

            $this->fail('Se modifico el historial.');
        } catch (\LogicException $e) {
            $this->assertSame(
                'El historial de precios no puede modificarse.',
                $e->getMessage()
            );
        }

        $this->assertSame(
            '500.0000',
            $historial->refresh()->precio_nuevo
        );
    }

    public function test_correccion_historial_impide_eliminacion(): void
    {
        $producto = $this->crearProducto();

        $historial = $producto->historialPrecios()->firstOrFail();

        try {
            $historial->delete();

            $this->fail('Se elimino el historial.');
        } catch (\LogicException $e) {
            $this->assertSame(
                'El historial de precios no puede eliminarse.',
                $e->getMessage()
            );
        }

        $this->assertDatabaseHas('historial_precios', [
            'id' => $historial->id,
            'precio_nuevo' => '500.0000',
        ]);
    }

    public function test_correccion_historial_conserva_usuario_eliminado(): void
    {
        $producto = $this->crearProducto();

        $usuarioId = $this->admin->id;
        $username = $this->admin->username;

        $this->admin->delete();

        $historial = $producto->historialPrecios()
            ->with('usuario')
            ->firstOrFail();

        $this->assertNotNull($historial->usuario);

        $this->assertEquals(
            $usuarioId,
            $historial->usuario->id
        );

        $this->assertSame(
            $username,
            $historial->usuario->username
        );

        $this->assertTrue($historial->usuario->trashed());
    }

    private function comprobarBloqueoLibre(): void
    {
        $conexion = DB::connection();

        $nombre = 'joysys:catalogos:' .
            sha1($conexion->getDatabaseName());

        $resultado = $conexion->selectOne(
            'SELECT IS_FREE_LOCK(?) AS libre',
            [$nombre],
            false
        );

        $this->assertSame(1, (int) $resultado->libre);
    }

    public function test_correccion_libera_bloqueo_despues_de_exito(): void
    {
        $this->comprobarBloqueoLibre();

        $producto = $this->crearProducto();

        $this->comprobarBloqueoLibre();

        $this->precios->actualizar(
            $producto,
            '650.0000',
            $this->admin
        );

        $this->comprobarBloqueoLibre();

        $this->productos->cambiarEstado(
            $producto,
            'inactivo',
            $this->admin
        );

        $this->comprobarBloqueoLibre();
    }

    public function test_correccion_libera_bloqueo_despues_de_fallo(): void
    {
        $dispatcherOriginal = HistorialPrecio::getEventDispatcher();

        HistorialPrecio::setEventDispatcher(
            clone $dispatcherOriginal
        );

        HistorialPrecio::creating(function () {
            throw new \RuntimeException('FALLO-BLOQUEO-PRUEBA');
        });

        try {
            $this->crearProducto();

            $this->fail('No se produjo el fallo esperado.');
        } catch (\RuntimeException $e) {
            $this->assertSame(
                'FALLO-BLOQUEO-PRUEBA',
                $e->getMessage()
            );
        } finally {
            HistorialPrecio::setEventDispatcher(
                $dispatcherOriginal
            );
        }

        $this->comprobarBloqueoLibre();

        $this->assertSame(0, Producto::count());
        $this->assertSame(0, PrecioProducto::count());
        $this->assertSame(0, HistorialPrecio::count());

        $producto = $this->crearProducto();

        $this->assertSame(
            '500.0000',
            $producto->precioVigente->precio
        );

        $this->comprobarBloqueoLibre();
    }
}
