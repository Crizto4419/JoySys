<?php

namespace Tests\Feature\Catalogos;

use App\Models\Categoria;
use App\Models\Metal;
use App\Models\Pureza;
use App\Models\Producto;
use App\Models\User;
use App\Services\Catalogos\CategoriaService;
use App\Services\Catalogos\MetalService;
use App\Services\Catalogos\PurezaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CatalogosIntegridadTest extends TestCase
{
    private function usuario(string $rol): User
    {
        $rolId = DB::table('roles')->insertGetId([
            'nombre' => ucfirst($rol),
            'slug' => $rol,
            'estado' => 'activo',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $permisos = [
            'dashboard.ver',
            'catalogos.ver',
        ];

        if ($rol === 'administrador') {
            $permisos = array_merge($permisos, [
                'catalogos.crear',
                'catalogos.editar',
                'catalogos.cambiar_estado',
            ]);
        }

        foreach ($permisos as $clave) {
            $permisoId = DB::table('permisos')
                ->where('clave', $clave)
                ->value('id');

            if (!$permisoId) {
                $permisoId = DB::table('permisos')->insertGetId([
                    'clave' => $clave,
                    'nombre' => $clave,
                    'modulo' => explode('.', $clave)[0],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('rol_permiso')->insert([
                'rol_id' => $rolId,
                'permiso_id' => $permisoId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return User::factory()->create([
            'role_id' => $rolId,
            'estado' => 'activo',
        ]);
    }

    private function categoria(): Categoria
    {
        return app(CategoriaService::class)->crear([
            'nombre' => 'Categoria de integridad',
            'categoria_padre_id' => null,
            'descripcion' => null,
        ]);
    }

    private function metal(): Metal
    {
        return app(MetalService::class)->crear([
            'nombre' => 'Metal de integridad',
            'simbolo' => 'MI',
            'descripcion' => null,
        ]);
    }

    private function pureza(Metal $metal): Pureza
    {
        return app(PurezaService::class)->crear([
            'metal_id' => $metal->id,
            'codigo' => '10K',
            'nombre' => 'Pureza de integridad',
        ]);
    }

    private function producto(
        Categoria $categoria,
        ?Metal $metal = null,
        ?Pureza $pureza = null
    ): Producto {
        return Producto::create([
            'categoria_id' => $categoria->id,
            'proveedor_id' => null,
            'metal_id' => $metal?->id,
            'pureza_id' => $pureza?->id,
            'codigo' => 'PRUEBA-001',
            'nombre' => 'Producto de integridad',
            'descripcion' => null,
            'estado' => 'activo',
            'tipo' => 'individual',
            'precio_base' => 100,
            'stock_minimo' => 0,
        ]);
    }

    private function sucursal(): int
    {
        return DB::table('sucursales')->insertGetId([
            'nombre' => 'Sucursal de pruebas',
            'codigo' => 'SUC-PRUEBA',
            'estado' => 'activa',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function estadoInventario(
        string $codigo = 'disponible'
    ): int {
        return DB::table('estados_inventario')->insertGetId([
            'codigo' => $codigo,
            'nombre' => ucfirst($codigo),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function comprobarRechazo(callable $operacion): void
    {
        try {
            $operacion();

            $this->fail('La operacion debio ser rechazada.');
        } catch (ValidationException $excepcion) {
            $this->assertNotEmpty($excepcion->errors());
        }
    }

    public function test_administrador_puede_consultar_catalogos(): void
    {
        $this->actingAs($this->usuario('administrador'));

        foreach ([
            'catalogos.index',
            'catalogos.categorias.index',
            'catalogos.metales.index',
            'catalogos.purezas.index',
        ] as $ruta) {
            $this->get(route($ruta))->assertOk();
        }
    }

    public function test_administrador_puede_registrar_editar_y_desactivar_categoria(): void
    {
        $this->actingAs($this->usuario('administrador'));

        $this->post(route('catalogos.categorias.store'), [
            'nombre' => 'Pulseras',
        ])->assertRedirect(route('catalogos.categorias.index'))
            ->assertSessionHasNoErrors();

        $categoria = Categoria::where('nombre', 'Pulseras')->firstOrFail();

        $this->put(route('catalogos.categorias.update', $categoria), [
            'nombre' => 'Pulseras infantiles',
        ])->assertRedirect(route('catalogos.categorias.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame(
            'Pulseras infantiles',
            $categoria->fresh()->nombre
        );

        $this->patch(route('catalogos.categorias.estado', $categoria), [
            'estado' => 'inactiva',
        ])->assertRedirect(route('catalogos.categorias.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame('inactiva', $categoria->fresh()->estado);
    }

    public function test_administrador_puede_registrar_editar_y_desactivar_metal(): void
    {
        $this->actingAs($this->usuario('administrador'));

        $this->post(route('catalogos.metales.store'), [
            'nombre' => 'Metal HTTP',
            'simbolo' => 'MH',
        ])->assertRedirect(route('catalogos.metales.index'))
            ->assertSessionHasNoErrors();

        $metal = Metal::where('nombre', 'Metal HTTP')->firstOrFail();

        $this->put(route('catalogos.metales.update', $metal), [
            'nombre' => 'Metal HTTP actualizado',
            'simbolo' => 'MHA',
        ])->assertRedirect(route('catalogos.metales.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame('MHA', $metal->fresh()->simbolo);

        $this->patch(route('catalogos.metales.estado', $metal), [
            'estado' => 'inactivo',
        ])->assertRedirect(route('catalogos.metales.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame('inactivo', $metal->fresh()->estado);
    }

    public function test_administrador_puede_registrar_y_editar_pureza(): void
    {
        $this->actingAs($this->usuario('administrador'));

        $metal = $this->metal();

        $this->post(route('catalogos.purezas.store'), [
            'metal_id' => $metal->id,
            'codigo' => '10k',
            'nombre' => 'Pureza HTTP',
        ])->assertRedirect(route('catalogos.purezas.index'))
            ->assertSessionHasNoErrors();

        $pureza = Pureza::where('metal_id', $metal->id)
            ->where('codigo', '10K')
            ->firstOrFail();

        $this->put(route('catalogos.purezas.update', $pureza), [
            'metal_id' => $metal->id,
            'codigo' => '14k',
            'nombre' => 'Pureza HTTP actualizada',
        ])->assertRedirect(route('catalogos.purezas.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame('14K', $pureza->fresh()->codigo);
    }

    private function comprobarRolDeConsulta(string $rol): void
    {
        $this->actingAs($this->usuario($rol));

        $categoria = $this->categoria();
        $metal = $this->metal();
        $pureza = $this->pureza($metal);

        $this->get(route('catalogos.index'))->assertOk();

        foreach ([
            'categorias' => $categoria,
            'metales' => $metal,
            'purezas' => $pureza,
        ] as $modulo => $modelo) {
            $prefijo = 'catalogos.'.$modulo.'.';

            $this->get(route($prefijo.'index'))
                ->assertOk();

            $this->get(route($prefijo.'create'))
                ->assertForbidden();

            $this->get(route($prefijo.'edit', $modelo))
                ->assertForbidden();

            $this->post(route($prefijo.'store'), [])
                ->assertForbidden();

            $this->put(route($prefijo.'update', $modelo), [])
                ->assertForbidden();

            if ($modulo !== 'purezas') {
                $this->patch(route($prefijo.'estado', $modelo), [])
                    ->assertForbidden();
            }
        }

        $this->assertSame('activa', $categoria->fresh()->estado);
        $this->assertSame('activo', $metal->fresh()->estado);
        $this->assertSame('10K', $pureza->fresh()->codigo);
    }

    public function test_encargado_solo_puede_consultar_catalogos(): void
    {
        $this->comprobarRolDeConsulta('encargado');
    }

    public function test_empleado_solo_puede_consultar_catalogos(): void
    {
        $this->comprobarRolDeConsulta('empleado');
    }

    public function test_categoria_con_producto_activo_no_se_desactiva(): void
    {
        $categoria = $this->categoria();

        $this->producto($categoria);

        $this->comprobarRechazo(
            fn () => app(CategoriaService::class)
                ->cambiarEstado($categoria, 'inactiva')
        );

        $this->assertSame('activa', $categoria->fresh()->estado);
    }

    public function test_metal_con_producto_activo_directo_no_se_desactiva(): void
    {
        $metal = $this->metal();

        $this->producto($this->categoria(), $metal);

        $this->comprobarRechazo(
            fn () => app(MetalService::class)
                ->cambiarEstado($metal, 'inactivo')
        );

        $this->assertSame('activo', $metal->fresh()->estado);
    }

    public function test_metal_con_producto_activo_indirecto_no_se_desactiva(): void
    {
        $metal = $this->metal();
        $pureza = $this->pureza($metal);

        $this->producto($this->categoria(), null, $pureza);

        $this->comprobarRechazo(
            fn () => app(MetalService::class)
                ->cambiarEstado($metal, 'inactivo')
        );

        $this->assertSame('activo', $metal->fresh()->estado);
    }

    public function test_pureza_referenciada_por_producto_no_se_modifica(): void
    {
        $metal = $this->metal();
        $pureza = $this->pureza($metal);

        $this->producto($this->categoria(), $metal, $pureza);

        $this->comprobarRechazo(
            fn () => app(PurezaService::class)->actualizar($pureza, [
                'metal_id' => $metal->id,
                'codigo' => '14K',
                'nombre' => 'Otra pureza',
            ])
        );

        $this->assertSame('10K', $pureza->fresh()->codigo);
    }

    public function test_pureza_con_producto_eliminado_logicamente_sigue_protegida(): void
    {
        $metal = $this->metal();
        $pureza = $this->pureza($metal);

        $producto = $this->producto(
            $this->categoria(),
            $metal,
            $pureza
        );

        $producto->delete();

        $this->assertTrue(
            app(PurezaService::class)->tieneReferencias($pureza)
        );

        $this->comprobarRechazo(
            fn () => app(PurezaService::class)->actualizar($pureza, [
                'metal_id' => $metal->id,
                'codigo' => '18K',
                'nombre' => 'Pureza modificada',
            ])
        );

        $this->assertSame('10K', $pureza->fresh()->codigo);
    }

    public function test_metal_con_pieza_disponible_no_se_desactiva(): void
    {
        $metal = $this->metal();
        $pureza = $this->pureza($metal);

        $producto = $this->producto(
            $this->categoria(),
            $metal,
            $pureza
        );

        $producto->update(['estado' => 'inactivo']);

        DB::table('piezas')->insert([
            'producto_id' => $producto->id,
            'sucursal_id' => $this->sucursal(),
            'estado_inventario_id' => $this->estadoInventario(),
            'metal_id' => $metal->id,
            'pureza_id' => $pureza->id,
            'codigo_pieza' => 'PIEZA-PRUEBA-001',
            'peso_gramos' => 2.5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->comprobarRechazo(
            fn () => app(MetalService::class)
                ->cambiarEstado($metal, 'inactivo')
        );

        $this->assertSame('activo', $metal->fresh()->estado);
    }

    public function test_pureza_referenciada_directamente_por_pieza_no_se_modifica(): void
    {
        $metal = $this->metal();
        $pureza = $this->pureza($metal);

        $producto = $this->producto($this->categoria());

        DB::table('piezas')->insert([
            'producto_id' => $producto->id,
            'sucursal_id' => $this->sucursal(),
            'estado_inventario_id' => $this->estadoInventario(),
            'metal_id' => $metal->id,
            'pureza_id' => $pureza->id,
            'codigo_pieza' => 'PIEZA-PRUEBA-002',
            'peso_gramos' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->comprobarRechazo(
            fn () => app(PurezaService::class)->actualizar($pureza, [
                'metal_id' => $metal->id,
                'codigo' => '18K',
                'nombre' => 'Pureza modificada',
            ])
        );

        $this->assertSame('10K', $pureza->fresh()->codigo);
    }

    public function test_metal_con_existencias_positivas_no_se_desactiva(): void
    {
        $metal = $this->metal();

        $producto = $this->producto(
            $this->categoria(),
            $metal
        );

        $producto->update([
            'estado' => 'inactivo',
            'tipo' => 'por_cantidad',
        ]);

        DB::table('existencias')->insert([
            'producto_id' => $producto->id,
            'sucursal_id' => $this->sucursal(),
            'estado_inventario_id' => $this->estadoInventario(),
            'cantidad' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->comprobarRechazo(
            fn () => app(MetalService::class)
                ->cambiarEstado($metal, 'inactivo')
        );

        $this->assertSame('activo', $metal->fresh()->estado);
    }

    public function test_formulario_categoria_rechaza_nombre_vacio(): void
    {
        $this->actingAs($this->usuario('administrador'));

        $this->post(route('catalogos.categorias.store'), [
            'nombre' => '',
        ])->assertSessionHasErrors('nombre');

        $this->assertDatabaseCount('categorias', 0);
    }

    public function test_formulario_categoria_rechaza_estado_manipulado(): void
    {
        $this->actingAs($this->usuario('administrador'));

        $this->post(route('catalogos.categorias.store'), [
            'nombre' => 'Categoria manipulada',
            'estado' => 'inactiva',
        ])->assertSessionHasErrors('estado');

        $this->assertDatabaseMissing('categorias', [
            'nombre' => 'Categoria manipulada',
        ]);
    }

    public function test_formulario_categoria_rechaza_descripcion_superior_a_text(): void
    {
        $this->actingAs($this->usuario('administrador'));

        $this->post(route('catalogos.categorias.store'), [
            'nombre' => 'Categoria extensa',
            'descripcion' => str_repeat('a', 65536),
        ])->assertSessionHasErrors('descripcion');

        $this->assertDatabaseMissing('categorias', [
            'nombre' => 'Categoria extensa',
        ]);
    }

    public function test_formulario_metal_rechaza_descripcion_superior_a_text(): void
    {
        $this->actingAs($this->usuario('administrador'));

        $this->post(route('catalogos.metales.store'), [
            'nombre' => 'Metal extenso',
            'descripcion' => str_repeat('a', 65536),
        ])->assertSessionHasErrors('descripcion');

        $this->assertDatabaseMissing('metales', [
            'nombre' => 'Metal extenso',
        ]);
    }

    public function test_formulario_pureza_rechaza_metal_inexistente(): void
    {
        $this->actingAs($this->usuario('administrador'));

        $this->post(route('catalogos.purezas.store'), [
            'metal_id' => 999999999,
            'codigo' => '10K',
            'nombre' => 'Pureza invalida',
        ])->assertSessionHasErrors('metal_id');

        $this->assertDatabaseMissing('purezas', [
            'nombre' => 'Pureza invalida',
        ]);
    }

    public function test_filtro_categoria_rechaza_arreglos(): void
    {
        $this->actingAs($this->usuario('administrador'));

        $this->get(route('catalogos.categorias.index', [
            'categoria_padre_id' => ['1', '2'],
        ]))->assertSessionHasErrors('categoria_padre_id');
    }

    public function test_filtro_metal_rechaza_estado_invalido(): void
    {
        $this->actingAs($this->usuario('administrador'));

        $this->get(route('catalogos.metales.index', [
            'estado' => 'desconocido',
        ]))->assertSessionHasErrors('estado');
    }

    public function test_filtro_pureza_rechaza_identificador_invalido(): void
    {
        $this->actingAs($this->usuario('administrador'));

        $this->get(route('catalogos.purezas.index', [
            'metal_id' => 'abc',
        ]))->assertSessionHasErrors('metal_id');
    }
}
