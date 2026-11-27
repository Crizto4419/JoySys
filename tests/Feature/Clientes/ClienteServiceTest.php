<?php

namespace Tests\Feature\Clientes;

use App\Models\Cliente;
use App\Models\User;
use App\Services\Clientes\ClienteService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ClienteServiceTest extends TestCase
{
    private ClienteService $servicio;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame(
            'joysys_testing',
            DB::connection()->getDatabaseName()
        );

        $this->servicio = app(ClienteService::class);

        $this->admin = $this->crearUsuario('administrador', [
            'clientes.ver',
            'clientes.crear',
            'clientes.editar',
            'clientes.desactivar',
            'dashboard.ver',
        ]);
    }

    private function crearUsuario(
        string $slug,
        array $permisos
    ): User {
        $rol = DB::table('roles')->insertGetId([
            'nombre' => ucfirst($slug),
            'slug' => $slug,
            'estado' => 'activo',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($permisos as $clave) {
            $permiso = DB::table('permisos')
                ->where('clave', $clave)
                ->value('id');

            if (!$permiso) {
                $permiso = DB::table('permisos')->insertGetId([
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

        return User::factory()->create([
            'role_id' => $rol,
            'estado' => 'activo',
        ]);
    }

    private function datos(array $cambios = []): array
    {
        return array_replace([
            'codigo' => 'CLI-TEST-001',
            'nombre' => 'Cliente de prueba',
            'telefono' => '+52 3312345678',
            'correo' => 'cliente@example.test',
        ], $cambios);
    }

    private function cliente(array $cambios = []): Cliente
    {
        return $this->servicio->crear(
            $this->datos($cambios),
            $this->admin
        );
    }

    private function validarError(
        callable $operacion,
        string $campo
    ): void {
        try {
            $operacion();

            $this->fail('Se esperaba un error de validacion.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey(
                $campo,
                $e->errors()
            );
        }
    }

    public function test_c01_registro_completo(): void
    {
        $cliente = $this->cliente();

        $this->assertTrue($cliente->activo);
        $this->assertSame('CLI-TEST-001', $cliente->codigo);
        $this->assertSame('Cliente de prueba', $cliente->nombre);
        $this->assertSame('+52 3312345678', $cliente->telefono);
        $this->assertSame('cliente@example.test', $cliente->correo);

        $this->assertDatabaseHas('clientes', [
            'id' => $cliente->id,
            'activo' => true,
            'deleted_at' => null,
        ]);
    }

    public function test_c02_registro_solo_nombre(): void
    {
        $cliente = $this->servicio->crear([
            'nombre' => 'Cliente sin contacto',
        ], $this->admin);

        $this->assertTrue($cliente->activo);
        $this->assertNull($cliente->codigo);
        $this->assertNull($cliente->telefono);
        $this->assertNull($cliente->correo);
    }

    public function test_c03_normalizacion(): void
    {
        $cliente = $this->cliente([
            'nombre' => '  Nombre normalizado  ',
            'codigo' => '   ',
            'telefono' => '',
            'correo' => '   ',
        ]);

        $this->assertSame('Nombre normalizado', $cliente->nombre);
        $this->assertNull($cliente->codigo);
        $this->assertNull($cliente->telefono);
        $this->assertNull($cliente->correo);
    }

    public function test_c04_duplicados_permitidos(): void
    {
        $primero = $this->cliente();
        $segundo = $this->cliente();

        $this->assertNotSame($primero->id, $segundo->id);
        $this->assertSame($primero->codigo, $segundo->codigo);
        $this->assertSame($primero->correo, $segundo->correo);
        $this->assertSame(2, Cliente::count());
    }

    public function test_c05_nombre_obligatorio(): void
    {
        foreach (['', '   ', null] as $nombre) {
            $this->validarError(
                fn () => $this->cliente(['nombre' => $nombre]),
                'nombre'
            );
        }

        $this->assertSame(0, Cliente::count());
    }

    public function test_c06_longitudes_maximas(): void
    {
        foreach ([
            'codigo' => str_repeat('A', 51),
            'nombre' => str_repeat('A', 181),
            'telefono' => str_repeat('1', 21),
            'correo' => str_repeat('a', 140) . '@example.test',
        ] as $campo => $valor) {
            $this->validarError(
                fn () => $this->cliente([$campo => $valor]),
                $campo
            );
        }

        $this->assertSame(0, Cliente::count());
    }

    public function test_c07_telefono_invalido(): void
    {
        foreach (['abc123', '---', '++521234'] as $telefono) {
            $this->validarError(
                fn () => $this->cliente(['telefono' => $telefono]),
                'telefono'
            );
        }

        $this->assertSame(0, Cliente::count());
    }

    public function test_c08_correo_invalido(): void
    {
        $this->validarError(
            fn () => $this->cliente(['correo' => 'correo-invalido']),
            'correo'
        );

        $this->assertSame(0, Cliente::count());
    }

    public function test_c09_proteccion_estado_registro(): void
    {
        $this->validarError(
            fn () => $this->cliente(['activo' => false]),
            'activo'
        );

        $this->assertSame(0, Cliente::count());
    }

    public function test_c10_actualizacion(): void
    {
        $cliente = $this->cliente();

        $actualizado = $this->servicio->actualizar(
            $cliente,
            $this->datos([
                'nombre' => 'Nombre actualizado',
                'telefono' => null,
            ]),
            $this->admin
        );

        $this->assertSame($cliente->id, $actualizado->id);
        $this->assertSame('Nombre actualizado', $actualizado->nombre);
        $this->assertNull($actualizado->telefono);
        $this->assertTrue($actualizado->activo);
        $this->assertSame(1, Cliente::count());
    }

    public function test_c11_edicion_inactivo(): void
    {
        $cliente = $this->cliente();

        $this->servicio->cambiarEstado(
            $cliente, 'inactivo', $this->admin
        );

        $registro = $this->servicio->actualizar(
            $cliente,
            $this->datos(['nombre' => 'Inactivo actualizado']),
            $this->admin
        );

        $this->assertFalse($registro->activo);
        $this->assertSame('Inactivo actualizado', $registro->nombre);
    }

    public function test_c12_desactivacion(): void
    {
        $cliente = $this->cliente();

        $registro = $this->servicio->cambiarEstado(
            $cliente, 'inactivo', $this->admin
        );

        $this->assertFalse($registro->activo);
        $this->assertNull($registro->deleted_at);
        $this->assertSame(1, Cliente::count());
    }

    public function test_c13_reactivacion(): void
    {
        $cliente = $this->cliente();

        $this->servicio->cambiarEstado(
            $cliente, 'inactivo', $this->admin
        );

        $registro = $this->servicio->cambiarEstado(
            $cliente, 'activo', $this->admin
        );

        $this->assertTrue($registro->activo);
        $this->assertSame($cliente->id, $registro->id);
        $this->assertNull($registro->deleted_at);
    }

    public function test_c14_estado_idempotente(): void
    {
        $cliente = $this->cliente();

        $anterior = $cliente->updated_at->copy();

        $this->travel(5)->seconds();

        try {
            $registro = $this->servicio->cambiarEstado(
                $cliente, 'activo', $this->admin
            );

            $this->assertTrue($registro->activo);
            $this->assertTrue(
                $anterior->equalTo($registro->updated_at)
            );
        } finally {
            $this->travelBack();
        }
    }

    public function test_c15_estado_invalido(): void
    {
        $cliente = $this->cliente();

        $this->validarError(
            fn () => $this->servicio->cambiarEstado(
                $cliente, 'eliminado', $this->admin
            ),
            'estado'
        );

        $this->assertTrue($cliente->fresh()->activo);
    }

    public function test_c16_busqueda_cuatro_campos(): void
    {
        $objetivo = $this->cliente([
            'codigo' => 'REF-UNICA',
            'nombre' => 'Nombre Especifico',
            'telefono' => '3312340000',
            'correo' => 'especifico@example.test',
        ]);

        $this->cliente([
            'codigo' => 'OTRO',
            'nombre' => 'Segundo registro',
            'telefono' => '3398760000',
            'correo' => 'otro@example.test',
        ]);

        foreach ([
            'REF-UNICA',
            'Nombre Especifico',
            '3312340000',
            'especifico@example.test',
        ] as $buscar) {
            $resultado = $this->servicio->listar(
                $this->admin,
                ['buscar' => $buscar]
            );

            $this->assertSame(1, $resultado->total());
            $this->assertSame(
                $objetivo->id,
                $resultado->items()[0]->id
            );
        }
    }

    public function test_c17_busqueda_literal(): void
    {
        $porcentaje = $this->cliente(['codigo' => 'REF%001']);
        $guion = $this->cliente(['codigo' => 'REF_001']);

        $this->cliente(['codigo' => 'REFX001']);

        foreach ([
            '%' => $porcentaje->id,
            '_' => $guion->id,
        ] as $buscar => $id) {
            $resultado = $this->servicio->listar(
                $this->admin,
                ['buscar' => $buscar]
            );

            $this->assertSame(1, $resultado->total());
            $this->assertSame($id, $resultado->items()[0]->id);
        }
    }

    public function test_c18_filtros_estado(): void
    {
        $activo = $this->cliente();
        $inactivo = $this->cliente();

        $this->servicio->cambiarEstado(
            $inactivo, 'inactivo', $this->admin
        );

        foreach ([
            'activo' => $activo->id,
            'inactivo' => $inactivo->id,
        ] as $estado => $id) {
            $resultado = $this->servicio->listar(
                $this->admin,
                ['estado' => $estado]
            );

            $this->assertSame(1, $resultado->total());
            $this->assertSame($id, $resultado->items()[0]->id);
        }
    }

    public function test_c19_paginacion(): void
    {
        for ($i = 1; $i <= 16; $i++) {
            $this->cliente([
                'nombre' => sprintf('Paginado %02d', $i),
            ]);
        }

        $this->actingAs($this->admin)
            ->get(route('clientes.index', [
                'buscar' => 'Paginado',
                'page' => 2,
            ]))
            ->assertOk()
            ->assertViewHas('clientes', function ($clientes) {
                return $clientes->total() === 16
                    && $clientes->perPage() === 15
                    && $clientes->currentPage() === 2
                    && $clientes->count() === 1
                    && $clientes->items()[0]->nombre === 'Paginado 16';
            });
    }

    public function test_c20_exclusion_soft_deleted(): void
    {
        $cliente = $this->cliente();

        // Preparacion de un registro historico exclusivamente en pruebas.
        DB::table('clientes')
            ->where('id', $cliente->id)
            ->update(['deleted_at' => now()]);

        $this->assertSame(
            0,
            $this->servicio->listar($this->admin)->total()
        );

        $this->assertSame(1, Cliente::withTrashed()->count());

        $this->actingAs($this->admin)
            ->get(route('clientes.show', $cliente))
            ->assertNotFound();
    }

    public function test_c21_invitados(): void
    {
        $this->get(route('clientes.index'))
            ->assertRedirect(route('login'));

        $this->post(route('clientes.store'), $this->datos())
            ->assertRedirect(route('login'));

        $this->assertSame(0, Cliente::count());
    }

    public function test_c22_renderizado_pantallas(): void
    {
        $cliente = $this->cliente();

        $this->actingAs($this->admin);

        $this->get(route('clientes.index'))
            ->assertOk()
            ->assertSee('Cliente de prueba');

        $this->get(route('clientes.create'))
            ->assertOk()
            ->assertSee('Guardar cliente');

        $this->get(route('clientes.show', $cliente))
            ->assertOk()
            ->assertSee('Desactivar cliente');

        $this->get(route('clientes.edit', $cliente))
            ->assertOk()
            ->assertSee('Guardar cambios');
    }

    public function test_c23_registro_http(): void
    {
        $respuesta = $this->actingAs($this->admin)
            ->post(route('clientes.store'), $this->datos());

        $cliente = Cliente::query()->sole();

        $respuesta
            ->assertRedirect(route('clientes.show', $cliente))
            ->assertSessionHasNoErrors();

        $this->assertTrue($cliente->activo);
    }

    public function test_c24_actualizacion_http(): void
    {
        $cliente = $this->cliente();

        $this->actingAs($this->admin)
            ->put(
                route('clientes.update', $cliente),
                $this->datos(['nombre' => 'Actualizado HTTP'])
            )
            ->assertRedirect(route('clientes.show', $cliente))
            ->assertSessionHasNoErrors();

        $this->assertSame(
            'Actualizado HTTP',
            $cliente->fresh()->nombre
        );
    }

    public function test_c25_cambio_estado_http(): void
    {
        $cliente = $this->cliente();

        $this->actingAs($this->admin);

        foreach ([
            'inactivo' => false,
            'activo' => true,
        ] as $estado => $activo) {
            $this->patch(
                route('clientes.estado', $cliente),
                ['estado' => $estado]
            )
                ->assertRedirect(route('clientes.show', $cliente))
                ->assertSessionHasNoErrors();

            $this->assertSame(
                $activo,
                $cliente->fresh()->activo
            );
        }
    }

    public function test_c26_permisos_empleado(): void
    {
        $empleado = $this->crearUsuario('empleado', [
            'clientes.ver',
            'clientes.crear',
            'clientes.editar',
        ]);

        $cliente = $this->cliente();

        $this->actingAs($empleado);

        $this->get(route('clientes.index'))->assertOk();
        $this->get(route('clientes.create'))->assertOk();
        $this->get(route('clientes.edit', $cliente))->assertOk();

        $this->get(route('clientes.show', $cliente))
            ->assertOk()
            ->assertDontSee('Desactivar cliente');

        $this->post(route('clientes.store'), $this->datos())
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->put(
            route('clientes.update', $cliente),
            $this->datos(['nombre' => 'Editado por empleado'])
        )
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('clientes.show', $cliente));

        $this->patch(
            route('clientes.estado', $cliente),
            ['estado' => 'inactivo']
        )->assertForbidden();

        $this->assertTrue($cliente->fresh()->activo);

        $this->expectException(AuthorizationException::class);

        $this->servicio->cambiarEstado(
            $cliente, 'inactivo', $empleado
        );
    }

    public function test_c27_permisos_encargado(): void
    {
        $encargado = $this->crearUsuario('encargado', [
            'clientes.ver',
            'clientes.crear',
            'clientes.editar',
        ]);

        $cliente = $this->cliente();

        $this->actingAs($encargado);

        $this->get(route('clientes.index'))->assertOk();
        $this->get(route('clientes.create'))->assertOk();
        $this->get(route('clientes.edit', $cliente))->assertOk();

        $this->post(route('clientes.store'), $this->datos())
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->put(
            route('clientes.update', $cliente),
            $this->datos(['nombre' => 'Editado por encargado'])
        )
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('clientes.show', $cliente));

        $this->patch(
            route('clientes.estado', $cliente),
            ['estado' => 'inactivo']
        )->assertForbidden();

        $this->assertTrue($cliente->fresh()->activo);
    }

    public function test_c28_acceso_sin_sucursal(): void
    {
        $this->assertDatabaseMissing('empleado_sucursal', [
            'usuario_id' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->get(route('clientes.index'))
            ->assertOk();

        $this->post(route('clientes.store'), $this->datos())
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame(1, Cliente::count());
    }

    public function test_c29_manipulacion_campos_http(): void
    {
        $cliente = $this->cliente();

        $this->actingAs($this->admin);

        $this->from(route('clientes.create'))
            ->post(
                route('clientes.store'),
                $this->datos([
                    'activo' => false,
                    'sucursal_id' => 999,
                    'deleted_at' => '2026-01-01',
                ])
            )
            ->assertSessionHasErrors([
                'activo',
                'sucursal_id',
                'deleted_at',
            ]);

        $this->from(route('clientes.edit', $cliente))
            ->put(
                route('clientes.update', $cliente),
                $this->datos(['activo' => false])
            )
            ->assertSessionHasErrors('activo');

        $this->from(route('clientes.show', $cliente))
            ->patch(
                route('clientes.estado', $cliente),
                [
                    'estado' => 'inactivo',
                    'nombre' => 'Manipulado',
                ]
            )
            ->assertSessionHasErrors('nombre');

        $this->assertTrue($cliente->fresh()->activo);
        $this->assertSame('Cliente de prueba', $cliente->fresh()->nombre);
        $this->assertSame(1, Cliente::count());
    }

    public function test_c30_sin_rutas_eliminacion(): void
    {
        $cliente = $this->cliente();

        $rutas = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($ruta) => str_starts_with(
                (string) $ruta->getName(),
                'clientes.'
            ));

        $this->assertCount(7, $rutas);

        foreach ($rutas as $ruta) {
            $this->assertNotContains('DELETE', $ruta->methods());
        }

        $this->assertFalse(Route::has('clientes.destroy'));

        $this->actingAs($this->admin)
            ->delete(route('clientes.show', $cliente))
            ->assertStatus(405);

        $this->assertSame(1, Cliente::count());
    }

    public function test_c31_integracion_dashboard(): void
    {
        $this->actingAs($this->admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Registrar cliente')
            ->assertSee(
                'href="' . route('clientes.index') . '"',
                false
            )
            ->assertSee(
                'href="' . route('clientes.create') . '"',
                false
            );
    }

    public function test_c32_usuario_sin_permisos(): void
    {
        $usuario = $this->crearUsuario('empleado', []);

        $cliente = $this->cliente();

        $this->actingAs($usuario);

        $this->get(route('clientes.index'))->assertForbidden();
        $this->get(route('clientes.create'))->assertForbidden();
        $this->get(route('clientes.show', $cliente))->assertForbidden();
        $this->get(route('clientes.edit', $cliente))->assertForbidden();

        $this->post(route('clientes.store'), $this->datos())
            ->assertForbidden();

        $this->put(
            route('clientes.update', $cliente),
            $this->datos()
        )->assertForbidden();

        $this->patch(
            route('clientes.estado', $cliente),
            ['estado' => 'inactivo']
        )->assertForbidden();

        $this->assertSame(1, Cliente::count());
        $this->assertTrue($cliente->fresh()->activo);
    }
}
