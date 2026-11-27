<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Dashboard\DashboardService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    private int $sucursalId;

    private int $otraSucursalId;

    private int $categoriaId;

    private int $disponibleId;

    private int $vendidoId;

    private int $cajaId;

    private User $administrador;

    private User $empleado;

    private int $secuencia = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(
            CarbonImmutable::parse(
                '2026-11-26 18:00:00',
                'UTC'
            )
        );

        $this->sucursalId = $this->crearSucursal(
            'Sucursal de pruebas',
            'SUC-TEST-001'
        );

        $this->otraSucursalId = $this->crearSucursal(
            'Sucursal secundaria',
            'SUC-TEST-002'
        );

        $this->administrador = $this->crearUsuario(
            'administrador',
            $this->sucursalId,
            [
                'dashboard.ver',
                'ventas.ver',
                'ventas.crear',
                'pagos.ver',
                'inventario.ver',
                'incidencias.ver',
                'incidencias.reportar',
                'caja.ver',
                'caja.abrir',
                'caja.cerrar',
                'catalogos.ver',
                'catalogos.crear',
                'clientes.ver',
                'clientes.crear',
                'reportes.ver',
            ]
        );

        $this->empleado = $this->crearUsuario(
            'empleado',
            $this->sucursalId,
            [
                'dashboard.ver',
                'ventas.ver',
                'ventas.crear',
                'pagos.ver',
                'inventario.ver',
                'incidencias.ver',
                'incidencias.reportar',
                'caja.ver',
                'caja.abrir',
                'caja.cerrar',
                'clientes.ver',
                'clientes.crear',
            ]
        );

        $this->categoriaId = DB::table('categorias')
            ->insertGetId([
                'nombre' => 'Categoría de pruebas',
                'estado' => 'activa',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        $this->disponibleId = DB::table(
            'estados_inventario'
        )->insertGetId([
            'codigo' => 'disponible',
            'nombre' => 'Disponible',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->vendidoId = DB::table(
            'estados_inventario'
        )->insertGetId([
            'codigo' => 'vendido',
            'nombre' => 'Vendido',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->cajaId = $this->crearCaja(
            $this->sucursalId,
            'CAJ-TEST-001'
        );
    }

    protected function tearDown(): void
    {
        $this->travelBack();

        parent::tearDown();
    }

    public function test_visitante_es_redirigido_al_login(): void
    {
        $this->get(route('dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_usuario_sin_permiso_recibe_403(): void
    {
        $usuario = $this->crearUsuario(
            'restringido',
            $this->sucursalId,
            []
        );

        $this->actingAs($usuario)
            ->get(route('dashboard'))
            ->assertForbidden();
    }

    public function test_dashboard_muestra_indicadores_vacios(): void
    {
        $resumen = $this->resumen(
            $this->administrador
        );

        $this->assertSame(
            0,
            $resumen['ventas']['cantidad']
        );

        $this->assertEquals(
            '0.0000',
            $resumen['ventas']['importe']
        );

        $this->assertEquals(
            '0.0000',
            $resumen['cobros']['importe']
        );

        $this->assertSame(
            0,
            $resumen['inventario']['piezas']
        );

        $this->assertEquals(
            '0.0000',
            $resumen['inventario']['unidades']
        );

        $this->assertEquals(
            '0.0000',
            $resumen['inventario']['gramos']
        );

        $this->assertSame(
            0,
            $resumen['incidencias']['total']
        );

        $this->assertSame(
            [],
            $resumen['ventas_recientes']
        );

        $this->actingAs($this->administrador)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSeeText('Ventas del día')
            ->assertSeeText('$0.00')
            ->assertSeeText('Inventario disponible')
            ->assertSeeText('Sin ventas recientes');
    }

    public function test_solo_contabiliza_ventas_completadas(): void
    {
        $this->crearVenta(
            $this->administrador,
            $this->sucursalId,
            '100.2500'
        );

        $this->crearVenta(
            $this->empleado,
            $this->sucursalId,
            '200.5000'
        );

        foreach (
            ['pendiente', 'cancelada', 'devuelta']
            as $estado
        ) {
            $this->crearVenta(
                $this->administrador,
                $this->sucursalId,
                '900.0000',
                $estado
            );
        }

        $resumen = $this->resumen(
            $this->administrador
        );

        $this->assertSame(
            2,
            $resumen['ventas']['cantidad']
        );

        $this->assertEquals(
            '300.7500',
            $resumen['ventas']['importe']
        );

        $this->assertCount(
            2,
            $resumen['ventas_recientes']
        );
    }

    public function test_respeta_limites_del_dia_operativo(): void
    {
        $fechas = [
            ['2026-11-26 05:59:59', '100.0000'],
            ['2026-11-26 06:00:00', '200.0000'],
            ['2026-11-27 05:59:59', '300.0000'],
            ['2026-11-27 06:00:00', '400.0000'],
        ];

        foreach ($fechas as [$fecha, $importe]) {
            $ventaId = $this->crearVenta(
                $this->administrador,
                $this->sucursalId,
                $importe,
                'completada',
                $fecha
            );

            $this->crearPago(
                $this->administrador,
                $this->sucursalId,
                $ventaId,
                $importe,
                'confirmado',
                'efectivo',
                null,
                $fecha
            );
        }

        $resumen = $this->resumen(
            $this->administrador
        );

        $this->assertSame(
            '26/11/2026',
            $resumen['fecha_operativa']
        );

        $this->assertSame(
            2,
            $resumen['ventas']['cantidad']
        );

        $this->assertEquals(
            '500.0000',
            $resumen['ventas']['importe']
        );

        $this->assertEquals(
            '500.0000',
            $resumen['cobros']['importe']
        );
    }

    public function test_solo_contabiliza_pagos_confirmados(): void
    {
        $ventaId = $this->crearVenta(
            $this->administrador,
            $this->sucursalId,
            '1000.0000'
        );

        $this->crearPago(
            $this->administrador,
            $this->sucursalId,
            $ventaId,
            '250.0000'
        );

        $this->crearPago(
            $this->administrador,
            $this->sucursalId,
            $ventaId,
            '300.0000',
            'pendiente'
        );

        $this->crearPago(
            $this->administrador,
            $this->sucursalId,
            $ventaId,
            '450.0000',
            'cancelado'
        );

        $resumen = $this->resumen(
            $this->administrador
        );

        $this->assertEquals(
            '250.0000',
            $resumen['cobros']['importe']
        );
    }

    public function test_excluye_reembolsos_y_saldos_a_favor(): void
    {
        $ventaId = $this->crearVenta(
            $this->administrador,
            $this->sucursalId,
            '500.0000'
        );

        $pagoId = $this->crearPago(
            $this->administrador,
            $this->sucursalId,
            $ventaId,
            '300.0000'
        );

        $detalleOriginalId = DB::table(
            'detalle_pagos'
        )
            ->where('pago_id', $pagoId)
            ->value('id');

        $this->crearPago(
            $this->administrador,
            $this->sucursalId,
            $ventaId,
            '100.0000',
            'confirmado',
            'efectivo',
            $pagoId,
            null,
            $detalleOriginalId
        );

        $this->crearPago(
            $this->administrador,
            $this->sucursalId,
            $ventaId,
            '200.0000',
            'confirmado',
            'saldo_favor'
        );

        $resumen = $this->resumen(
            $this->administrador
        );

        $this->assertEquals(
            '300.0000',
            $resumen['cobros']['importe']
        );
    }

    public function test_separa_modalidades_de_inventario(): void
    {
        $individual = $this->crearProducto(
            'individual'
        );

        $cantidad = $this->crearProducto(
            'por_cantidad'
        );

        $peso = $this->crearProducto(
            'por_peso'
        );

        $this->crearPieza(
            $individual,
            $this->sucursalId,
            $this->disponibleId
        );

        $this->crearPieza(
            $individual,
            $this->sucursalId,
            $this->disponibleId
        );

        $this->crearExistencia(
            $cantidad,
            $this->sucursalId,
            $this->disponibleId,
            '15.0000'
        );

        $this->crearExistencia(
            $peso,
            $this->sucursalId,
            $this->disponibleId,
            '24.7500'
        );

        $resumen = $this->resumen(
            $this->administrador
        );

        $this->assertSame(
            2,
            $resumen['inventario']['piezas']
        );

        $this->assertEquals(
            '15.0000',
            $resumen['inventario']['unidades']
        );

        $this->assertEquals(
            '24.7500',
            $resumen['inventario']['gramos']
        );
    }

    public function test_excluye_inventario_no_disponible(): void
    {
        $individual = $this->crearProducto(
            'individual'
        );

        $cantidad = $this->crearProducto(
            'por_cantidad'
        );

        $peso = $this->crearProducto(
            'por_peso'
        );

        $this->crearPieza(
            $individual,
            $this->sucursalId,
            $this->vendidoId
        );

        $this->crearExistencia(
            $cantidad,
            $this->sucursalId,
            $this->vendidoId,
            '20.0000'
        );

        $this->crearExistencia(
            $peso,
            $this->sucursalId,
            $this->vendidoId,
            '30.0000'
        );

        $resumen = $this->resumen(
            $this->administrador
        );

        $this->assertSame(
            0,
            $resumen['inventario']['piezas']
        );

        $this->assertEquals(
            '0.0000',
            $resumen['inventario']['unidades']
        );

        $this->assertEquals(
            '0.0000',
            $resumen['inventario']['gramos']
        );
    }

    public function test_excluye_productos_inactivos_y_eliminados(): void
    {
        $inactivo = $this->crearProducto(
            'individual',
            'inactivo'
        );

        $eliminado = $this->crearProducto(
            'por_cantidad',
            'activo',
            true
        );

        $this->crearPieza(
            $inactivo,
            $this->sucursalId,
            $this->disponibleId
        );

        $this->crearExistencia(
            $eliminado,
            $this->sucursalId,
            $this->disponibleId,
            '25.0000'
        );

        $resumen = $this->resumen(
            $this->administrador
        );

        $this->assertSame(
            0,
            $resumen['inventario']['piezas']
        );

        $this->assertEquals(
            '0.0000',
            $resumen['inventario']['unidades']
        );
    }

    public function test_contabiliza_incidencias_pendientes(): void
    {
        $productoId = $this->crearProducto(
            'individual'
        );

        foreach (
            ['abierta', 'en_revision', 'resuelta']
            as $estado
        ) {
            $this->crearIncidencia(
                $productoId,
                $this->sucursalId,
                $estado
            );
        }

        $resumen = $this->resumen(
            $this->administrador
        );

        $this->assertSame(
            2,
            $resumen['incidencias']['total']
        );

        $this->assertSame(
            1,
            $resumen['incidencias']['abiertas']
        );

        $this->assertSame(
            1,
            $resumen['incidencias']['en_revision']
        );
    }

    public function test_muestra_estado_operativo_de_caja(): void
    {
        $this->crearSesionCaja(
            $this->empleado,
            $this->cajaId,
            $this->sucursalId
        );

        $resumenAdmin = $this->resumen(
            $this->administrador
        );

        $resumenEmpleado = $this->resumen(
            $this->empleado
        );

        $this->assertSame(
            1,
            $resumenAdmin['caja']['cajas_activas']
        );

        $this->assertSame(
            1,
            $resumenAdmin['caja']['sesiones_abiertas']
        );

        $this->assertNull(
            $resumenAdmin['caja']['sesion_propia']
        );

        $this->assertNull(
            $resumenEmpleado['caja']['sesiones_abiertas']
        );

        $this->assertNotNull(
            $resumenEmpleado['caja']['sesion_propia']
        );

        $this->assertSame(
            'CAJ-TEST-001',
            $resumenEmpleado['caja']['sesion_propia']->codigo
        );

        $this->assertObjectNotHasProperty(
            'fondo_inicial',
            $resumenEmpleado['caja']['sesion_propia']
        );

        $this->assertObjectNotHasProperty(
            'saldo_esperado',
            $resumenEmpleado['caja']['sesion_propia']
        );
    }

    public function test_aisla_informacion_entre_sucursales(): void
    {
        $otroUsuario = $this->crearUsuario(
            'operador_secundario',
            $this->otraSucursalId,
            ['dashboard.ver']
        );

        $ventaId = $this->crearVenta(
            $otroUsuario,
            $this->otraSucursalId,
            '900.0000'
        );

        $this->crearPago(
            $otroUsuario,
            $this->otraSucursalId,
            $ventaId,
            '900.0000'
        );

        $productoId = $this->crearProducto(
            'individual'
        );

        $this->crearPieza(
            $productoId,
            $this->otraSucursalId,
            $this->disponibleId
        );

        $productoCantidad = $this->crearProducto(
            'por_cantidad'
        );

        $this->crearExistencia(
            $productoCantidad,
            $this->otraSucursalId,
            $this->disponibleId,
            '50.0000'
        );

        $productoPeso = $this->crearProducto(
            'por_peso'
        );

        $this->crearExistencia(
            $productoPeso,
            $this->otraSucursalId,
            $this->disponibleId,
            '80.0000'
        );

        $this->crearIncidencia(
            $productoId,
            $this->otraSucursalId,
            'abierta'
        );

        $otraCaja = $this->crearCaja(
            $this->otraSucursalId,
            'CAJ-TEST-002'
        );

        $this->crearSesionCaja(
            $otroUsuario,
            $otraCaja,
            $this->otraSucursalId
        );

        $resumen = $this->resumen(
            $this->administrador
        );

        $this->assertSame(
            0,
            $resumen['ventas']['cantidad']
        );

        $this->assertEquals(
            '0.0000',
            $resumen['cobros']['importe']
        );

        $this->assertSame(
            0,
            $resumen['inventario']['piezas']
        );

        $this->assertEquals(
            '0.0000',
            $resumen['inventario']['unidades']
        );

        $this->assertEquals(
            '0.0000',
            $resumen['inventario']['gramos']
        );

        $this->assertSame(
            0,
            $resumen['incidencias']['total']
        );

        $this->assertSame(
            1,
            $resumen['caja']['cajas_activas']
        );

        $this->assertSame(
            0,
            $resumen['caja']['sesiones_abiertas']
        );

        $this->assertSame(
            [],
            $resumen['ventas_recientes']
        );
    }

    public function test_empleado_solo_consulta_operaciones_propias(): void
    {
        $ventaAdmin = $this->crearVenta(
            $this->administrador,
            $this->sucursalId,
            '800.0000'
        );

        $ventaEmpleado = $this->crearVenta(
            $this->empleado,
            $this->sucursalId,
            '150.0000'
        );

        $this->crearPago(
            $this->administrador,
            $this->sucursalId,
            $ventaAdmin,
            '800.0000'
        );

        $this->crearPago(
            $this->empleado,
            $this->sucursalId,
            $ventaEmpleado,
            '150.0000'
        );

        $resumen = $this->resumen(
            $this->empleado
        );

        $this->assertSame(
            1,
            $resumen['ventas']['cantidad']
        );

        $this->assertEquals(
            '150.0000',
            $resumen['ventas']['importe']
        );

        $this->assertEquals(
            '150.0000',
            $resumen['cobros']['importe']
        );

        $this->assertSame(
            'propio',
            $resumen['ventas']['alcance']
        );

        $this->assertSame(
            'propio',
            $resumen['cobros']['alcance']
        );

        $this->assertCount(
            1,
            $resumen['ventas_recientes']
        );

        $this->assertEquals(
            $ventaEmpleado,
            $resumen['ventas_recientes'][0]->id
        );
    }

    public function test_oculta_indicadores_sin_permiso(): void
    {
        $usuario = $this->crearUsuario(
            'limitado',
            $this->sucursalId,
            ['dashboard.ver']
        );

        DB::enableQueryLog();

        try {
            $resumen = $this->resumen($usuario);

            $consultas = DB::getQueryLog();
        } finally {
            DB::disableQueryLog();

            DB::flushQueryLog();
        }

        foreach (
            [
                'ventas',
                'cobros',
                'inventario',
                'incidencias',
                'caja',
                'ventas_recientes',
            ] as $indicador
        ) {
            $this->assertNull(
                $resumen[$indicador]
            );
        }

        $tablasRestringidas = [
            'ventas',
            'pagos',
            'detalle_pagos',
            'piezas',
            'existencias',
            'productos',
            'estados_inventario',
            'incidencias_inventario',
            'cajas',
            'sesiones_caja',
        ];

        foreach ($consultas as $consulta) {
            foreach ($tablasRestringidas as $tabla) {
                $this->assertStringNotContainsString(
                    '`'.$tabla.'`',
                    strtolower($consulta['query'])
                );
            }
        }

        $this->actingAs($usuario)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSeeText('Ventas del día')
            ->assertDontSeeText('Cobros del día')
            ->assertDontSeeText('Inventario disponible')
            ->assertDontSeeText('Incidencias pendientes')
            ->assertDontSeeText('Estado de caja');
    }

    public function test_dashboard_funciona_sin_sucursal_operativa(): void
    {
        $usuario = $this->crearUsuario(
            'sin_sucursal',
            null,
            [
                'dashboard.ver',
                'ventas.ver',
                'ventas.crear',
                'pagos.ver',
                'inventario.ver',
                'incidencias.ver',
                'caja.ver',
            ]
        );

        $resumen = $this->resumen($usuario);

        $this->assertNull(
            $resumen['sucursal_operativa']
        );

        foreach (
            [
                'ventas',
                'cobros',
                'inventario',
                'incidencias',
                'caja',
                'ventas_recientes',
            ] as $indicador
        ) {
            $this->assertNull(
                $resumen[$indicador]
            );
        }

        $this->actingAs($usuario)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSeeText('Operaciones restringidas')
            ->assertSeeText('Sin sucursal operativa')
            ->assertSeeText('Sucursal requerida')
            ->assertDontSeeText('Ventas del día');
    }

    public function test_limita_ventas_recientes_a_cinco(): void
    {
        $ids = [];

        for ($i = 0; $i < 7; $i++) {
            $ids[] = $this->crearVenta(
                $this->administrador,
                $this->sucursalId,
                '100.0000',
                'completada',
                '2026-11-26 18:00:00'
            );
        }

        $this->crearVenta(
            $this->administrador,
            $this->sucursalId,
            '100.0000',
            'cancelada',
            '2026-11-26 19:00:00'
        );

        $resumen = $this->resumen(
            $this->administrador
        );

        $this->assertCount(
            5,
            $resumen['ventas_recientes']
        );

        $idsObtenidos = array_map(
            fn ($venta) => (int) $venta->id,
            $resumen['ventas_recientes']
        );

        $this->assertSame(
            array_slice(array_reverse($ids), 0, 5),
            $idsObtenidos
        );
    }

    public function test_accesos_rapidos_respetan_permisos(): void
    {
        $resumenAdmin = $this->resumen(
            $this->administrador
        );

        $resumenEmpleado = $this->resumen(
            $this->empleado
        );

        $this->assertCount(
            6,
            $resumenAdmin['accesos_rapidos']
        );

        $this->assertCount(
            5,
            $resumenEmpleado['accesos_rapidos']
        );

        $permisos = array_column(
            $resumenEmpleado['accesos_rapidos'],
            'permiso'
        );

        $this->assertNotContains(
            'catalogos.crear',
            $permisos
        );

        $this->assertContains(
            'ventas.crear',
            $permisos
        );

        foreach (
            $resumenAdmin['accesos_rapidos']
            as $acceso
        ) {
            $this->assertNull($acceso['ruta']);
        }

        $this->actingAs($this->empleado)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSeeText('Nueva venta')
            ->assertDontSeeText('Registrar producto');
    }

    public function test_conexion_mysql_utiliza_utc(): void
    {
        $resultado = DB::selectOne(
            'SELECT
                @@session.time_zone AS zona,
                NOW() AS hora_mysql,
                UTC_TIMESTAMP() AS hora_utc'
        );

        $this->assertSame(
            '+00:00',
            $resultado->zona
        );

        $this->assertSame(
            $resultado->hora_mysql,
            $resultado->hora_utc
        );

        $this->assertSame(
            'UTC',
            config('app.timezone')
        );

        $this->assertSame(
            'America/Mexico_City',
            config('dashboard.zona_horaria')
        );
    }

    public function test_sin_reportes_el_alcance_financiero_es_propio(): void
    {
        $permisoId = DB::table('permisos')
            ->where('clave', 'reportes.ver')
            ->value('id');

        DB::table('rol_permiso')
            ->where(
                'rol_id',
                $this->administrador->role_id
            )
            ->where('permiso_id', $permisoId)
            ->delete();

        $this->crearVenta(
            $this->administrador,
            $this->sucursalId,
            '100.0000'
        );

        $this->crearVenta(
            $this->empleado,
            $this->sucursalId,
            '200.0000'
        );

        $resumen = $this->resumen(
            $this->administrador
        );

        $this->assertSame(
            'propio',
            $resumen['ventas']['alcance']
        );

        $this->assertSame(
            1,
            $resumen['ventas']['cantidad']
        );

        $this->assertEquals(
            '100.0000',
            $resumen['ventas']['importe']
        );
    }

    public function test_administrador_sin_reportes_conserva_alcance_de_caja(): void
    {
        $permisoId = DB::table('permisos')
            ->where('clave', 'reportes.ver')
            ->value('id');

        DB::table('rol_permiso')
            ->where('rol_id', $this->administrador->role_id)
            ->where('permiso_id', $permisoId)
            ->delete();

        $ventaAdmin = $this->crearVenta(
            $this->administrador,
            $this->sucursalId,
            '100.0000'
        );

        $ventaEmpleado = $this->crearVenta(
            $this->empleado,
            $this->sucursalId,
            '200.0000'
        );

        $this->crearPago(
            $this->administrador,
            $this->sucursalId,
            $ventaAdmin,
            '100.0000'
        );

        $this->crearPago(
            $this->empleado,
            $this->sucursalId,
            $ventaEmpleado,
            '200.0000'
        );

        $this->crearSesionCaja(
            $this->empleado,
            $this->cajaId,
            $this->sucursalId
        );

        $resumen = $this->resumen($this->administrador);

        $this->assertSame('propio', $resumen['ventas']['alcance']);
        $this->assertSame(1, $resumen['ventas']['cantidad']);
        $this->assertSame('100.0000', $resumen['ventas']['importe']);
        $this->assertSame('propio', $resumen['cobros']['alcance']);
        $this->assertSame('100.0000', $resumen['cobros']['importe']);
        $this->assertCount(1, $resumen['ventas_recientes']);
        $this->assertEquals($ventaAdmin, $resumen['ventas_recientes'][0]->id);
        $this->assertSame('sucursal', $resumen['caja']['alcance']);
        $this->assertSame(1, $resumen['caja']['sesiones_abiertas']);
        $this->assertNull($resumen['caja']['sesion_propia']);
    }

    public function test_encargado_sin_reportes_conserva_alcance_de_caja(): void
    {
        $encargado = $this->crearUsuario(
            'encargado',
            $this->sucursalId,
            [
                'dashboard.ver',
                'ventas.ver',
                'pagos.ver',
                'caja.ver',
            ]
        );

        $ventaEncargado = $this->crearVenta(
            $encargado,
            $this->sucursalId,
            '150.0000'
        );

        $ventaEmpleado = $this->crearVenta(
            $this->empleado,
            $this->sucursalId,
            '350.0000'
        );

        $this->crearPago(
            $encargado,
            $this->sucursalId,
            $ventaEncargado,
            '150.0000'
        );

        $this->crearPago(
            $this->empleado,
            $this->sucursalId,
            $ventaEmpleado,
            '350.0000'
        );

        $this->crearSesionCaja(
            $this->empleado,
            $this->cajaId,
            $this->sucursalId
        );

        $resumen = $this->resumen($encargado);

        $this->assertSame('propio', $resumen['ventas']['alcance']);
        $this->assertSame(1, $resumen['ventas']['cantidad']);
        $this->assertSame('150.0000', $resumen['ventas']['importe']);
        $this->assertSame('propio', $resumen['cobros']['alcance']);
        $this->assertSame('150.0000', $resumen['cobros']['importe']);
        $this->assertCount(1, $resumen['ventas_recientes']);
        $this->assertEquals($ventaEncargado, $resumen['ventas_recientes'][0]->id);
        $this->assertSame('sucursal', $resumen['caja']['alcance']);
        $this->assertSame(1, $resumen['caja']['sesiones_abiertas']);
        $this->assertNull($resumen['caja']['sesion_propia']);
    }

    public function test_encargado_con_reportes_obtiene_alcance_de_sucursal(): void
    {
        $encargado = $this->crearUsuario(
            'encargado',
            $this->sucursalId,
            [
                'dashboard.ver',
                'ventas.ver',
                'pagos.ver',
                'caja.ver',
                'reportes.ver',
            ]
        );

        $ventaEncargado = $this->crearVenta(
            $encargado,
            $this->sucursalId,
            '150.0000'
        );

        $ventaEmpleado = $this->crearVenta(
            $this->empleado,
            $this->sucursalId,
            '350.0000'
        );

        $this->crearPago(
            $encargado,
            $this->sucursalId,
            $ventaEncargado,
            '150.0000'
        );

        $this->crearPago(
            $this->empleado,
            $this->sucursalId,
            $ventaEmpleado,
            '350.0000'
        );

        $this->crearSesionCaja(
            $this->empleado,
            $this->cajaId,
            $this->sucursalId
        );

        $resumen = $this->resumen($encargado);

        $this->assertSame('sucursal', $resumen['ventas']['alcance']);
        $this->assertSame(2, $resumen['ventas']['cantidad']);
        $this->assertSame('500.0000', $resumen['ventas']['importe']);
        $this->assertSame('sucursal', $resumen['cobros']['alcance']);
        $this->assertSame('500.0000', $resumen['cobros']['importe']);
        $this->assertCount(2, $resumen['ventas_recientes']);
        $this->assertSame('sucursal', $resumen['caja']['alcance']);
        $this->assertSame(1, $resumen['caja']['sesiones_abiertas']);
    }

    public function test_empleado_con_reportes_conserva_alcance_propio(): void
    {
        $permisoId = DB::table('permisos')
            ->where('clave', 'reportes.ver')
            ->value('id');

        DB::table('rol_permiso')->insert([
            'rol_id' => $this->empleado->role_id,
            'permiso_id' => $permisoId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $ventaAdmin = $this->crearVenta(
            $this->administrador,
            $this->sucursalId,
            '800.0000'
        );

        $ventaEmpleado = $this->crearVenta(
            $this->empleado,
            $this->sucursalId,
            '150.0000'
        );

        $this->crearPago(
            $this->administrador,
            $this->sucursalId,
            $ventaAdmin,
            '800.0000'
        );

        $this->crearPago(
            $this->empleado,
            $this->sucursalId,
            $ventaEmpleado,
            '150.0000'
        );

        $otraCaja = $this->crearCaja(
            $this->sucursalId,
            'CAJ-TEST-ADICIONAL'
        );

        $this->crearSesionCaja(
            $this->administrador,
            $otraCaja,
            $this->sucursalId
        );

        $sesionEmpleadoId = $this->crearSesionCaja(
            $this->empleado,
            $this->cajaId,
            $this->sucursalId
        );

        $resumen = $this->resumen($this->empleado);

        $this->assertTrue(
            $this->empleado->can('joysys.permiso', 'reportes.ver')
        );

        $this->assertSame('propio', $resumen['ventas']['alcance']);
        $this->assertSame(1, $resumen['ventas']['cantidad']);
        $this->assertSame('150.0000', $resumen['ventas']['importe']);
        $this->assertSame('propio', $resumen['cobros']['alcance']);
        $this->assertSame('150.0000', $resumen['cobros']['importe']);
        $this->assertCount(1, $resumen['ventas_recientes']);
        $this->assertEquals($ventaEmpleado, $resumen['ventas_recientes'][0]->id);
        $this->assertSame('propio', $resumen['caja']['alcance']);
        $this->assertNull($resumen['caja']['sesiones_abiertas']);
        $this->assertNotNull($resumen['caja']['sesion_propia']);
        $this->assertEquals($sesionEmpleadoId, $resumen['caja']['sesion_propia']->id);
    }

    public function test_administrador_sin_permiso_de_caja_no_obtiene_indicadores(): void
    {
        $permisoId = DB::table('permisos')
            ->where('clave', 'caja.ver')
            ->value('id');

        DB::table('rol_permiso')
            ->where('rol_id', $this->administrador->role_id)
            ->where('permiso_id', $permisoId)
            ->delete();

        $this->crearSesionCaja(
            $this->empleado,
            $this->cajaId,
            $this->sucursalId
        );

        $resumen = $this->resumen($this->administrador);

        $this->assertNull($resumen['caja']);
        $this->assertSame('sucursal', $resumen['ventas']['alcance']);
        $this->assertSame('sucursal', $resumen['cobros']['alcance']);

        $this->actingAs($this->administrador)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSeeText('Estado de caja');
    }

    private function resumen(User $usuario): array
    {
        return app(DashboardService::class)
            ->obtenerResumen($usuario);
    }

    private function crearSucursal(
        string $nombre,
        string $codigo
    ): int {
        return DB::table('sucursales')
            ->insertGetId([
                'nombre' => $nombre,
                'codigo' => $codigo,
                'estado' => 'activa',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }

    private function crearUsuario(
        string $rol,
        ?int $sucursalId,
        array $permisos
    ): User {
        $rolId = DB::table('roles')
            ->insertGetId([
                'nombre' => ucfirst($rol),
                'slug' => $rol,
                'estado' => 'activo',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        foreach ($permisos as $clave) {
            $permisoId = DB::table('permisos')
                ->where('clave', $clave)
                ->value('id');

            if ($permisoId === null) {
                $permisoId = DB::table('permisos')
                    ->insertGetId([
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

        $numero = ++$this->secuencia;

        $usuarioId = DB::table('users')
            ->insertGetId([
                'username' => 'dashboard_test_'.$numero,
                'name' => 'Usuario',
                'apellidos' => 'De prueba',
                'codigo_empleado' => 'DASH-'.$numero,
                'email' => 'dashboard'.$numero.'@joysys.test',
                'password' => Hash::make(
                    'JoySys-Test-Only-2026!'
                ),
                'role_id' => $rolId,
                'estado' => 'activo',
                'intentos_fallidos' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        if ($sucursalId !== null) {
            DB::table('empleado_sucursal')->insert([
                'usuario_id' => $usuarioId,
                'sucursal_id' => $sucursalId,
                'es_principal' => true,
                'fecha_inicio' => '2026-01-01',
                'fecha_fin' => null,
                'estado' => 'activo',
                'asignado_por' => $usuarioId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return User::findOrFail($usuarioId);
    }

    private function crearVenta(
        User $usuario,
        int $sucursalId,
        string $importe,
        string $estado = 'completada',
        ?string $fecha = null
    ): int {
        return DB::table('ventas')->insertGetId([
            'sucursal_id' => $sucursalId,
            'cliente_id' => null,
            'usuario_id' => $usuario->id,
            'folio' => 'VENTA-TEST-'.(++$this->secuencia),
            'subtotal' => $importe,
            'descuento_total' => '0.0000',
            'impuestos' => '0.0000',
            'total' => $importe,
            'estado' => $estado,
            'created_at' => $fecha ?? now(),
            'updated_at' => $fecha ?? now(),
        ]);
    }

    private function crearPago(
        User $usuario,
        int $sucursalId,
        int $ventaId,
        string $importe,
        string $estado = 'confirmado',
        string $metodo = 'efectivo',
        ?int $pagoOriginalId = null,
        ?string $fecha = null,
        ?int $detalleOriginalId = null
    ): int {
        $pagoId = DB::table('pagos')
            ->insertGetId([
                'sucursal_id' => $sucursalId,
                'referencia_tipo' => 'venta',
                'referencia_id' => $ventaId,
                'pago_original_id' => $pagoOriginalId,
                'recibido_por' => $usuario->id,
                'monto_total' => $importe,
                'moneda' => 'MXN',
                'estado' => $estado,
                'fecha_pago' => $estado === 'confirmado'
                    ? ($fecha ?? now())
                    : null,
                'created_at' => $fecha ?? now(),
            ]);

        DB::table('detalle_pagos')->insert([
            'pago_id' => $pagoId,
            'metodo_pago' => $metodo,
            'monto' => $importe,
            'detalle_pago_original_id' => $detalleOriginalId,
            'created_at' => $fecha ?? now(),
        ]);

        return $pagoId;
    }

    private function crearProducto(
        string $tipo,
        string $estado = 'activo',
        bool $eliminado = false
    ): int {
        $numero = ++$this->secuencia;

        return DB::table('productos')
            ->insertGetId([
                'categoria_id' => $this->categoriaId,
                'codigo' => 'PROD-TEST-'.$numero,
                'nombre' => 'Producto de prueba '.$numero,
                'estado' => $estado,
                'tipo' => $tipo,
                'precio_base' => '100.0000',
                'stock_minimo' => '0.0000',
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => $eliminado
                    ? now()
                    : null,
            ]);
    }

    private function crearPieza(
        int $productoId,
        int $sucursalId,
        int $estadoId
    ): int {
        return DB::table('piezas')
            ->insertGetId([
                'producto_id' => $productoId,
                'sucursal_id' => $sucursalId,
                'estado_inventario_id' => $estadoId,
                'codigo_pieza' => 'PIEZA-TEST-'.(++$this->secuencia),
                'peso_gramos' => '5.250',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }

    private function crearExistencia(
        int $productoId,
        int $sucursalId,
        int $estadoId,
        string $cantidad
    ): int {
        return DB::table('existencias')
            ->insertGetId([
                'producto_id' => $productoId,
                'sucursal_id' => $sucursalId,
                'estado_inventario_id' => $estadoId,
                'cantidad' => $cantidad,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }

    private function crearIncidencia(
        int $productoId,
        int $sucursalId,
        string $estado
    ): int {
        return DB::table('incidencias_inventario')
            ->insertGetId([
                'producto_id' => $productoId,
                'sucursal_id' => $sucursalId,
                'usuario_reporta_id' => $this->administrador->id,
                'tipo_incidencia' => 'diferencia',
                'descripcion' => 'Incidencia de prueba.',
                'estado' => $estado,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }

    private function crearCaja(
        int $sucursalId,
        string $codigo
    ): int {
        return DB::table('cajas')->insertGetId([
            'sucursal_id' => $sucursalId,
            'codigo' => $codigo,
            'nombre' => 'Caja de pruebas',
            'moneda' => 'MXN',
            'estado' => 'activa',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function crearSesionCaja(
        User $usuario,
        int $cajaId,
        int $sucursalId
    ): int {
        return DB::table('sesiones_caja')
            ->insertGetId([
                'caja_id' => $cajaId,
                'sucursal_id' => $sucursalId,
                'usuario_id' => $usuario->id,
                'fondo_inicial' => '500.0000',
                'fecha_apertura' => now(),
                'estado' => 'abierta',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }
}