<?php

namespace App\Services\Dashboard;

use App\Models\Sucursal;
use App\Models\User;
use App\Services\Auth\ContextoSucursalService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function __construct(
        private readonly ContextoSucursalService $contextoSucursal
    ) {
    }

    public function obtenerResumen(User $usuario): array
    {
        abort_unless(
            $usuario->can('joysys.permiso', 'dashboard.ver'),
            403
        );

        $sucursal = $this->contextoSucursal
            ->obtenerSucursal($usuario);

        $zonaHoraria = config(
            'dashboard.zona_horaria',
            'America/Mexico_City'
        );

        $ahoraLocal = CarbonImmutable::now($zonaHoraria);

        $inicioLocal = $ahoraLocal->startOfDay();

        $finLocal = $inicioLocal->addDay();

        $inicioUtc = $inicioLocal->utc();

        $finUtc = $finLocal->utc();

        $resumen = [
            'fecha_operativa' => $ahoraLocal->format('d/m/Y'),
            'zona_horaria' => $zonaHoraria,
            'sucursal_operativa' => $sucursal,
            'ventas' => null,
            'cobros' => null,
            'inventario' => null,
            'incidencias' => null,
            'caja' => null,
            'ventas_recientes' => null,
            'accesos_rapidos' => $this->obtenerAccesosRapidos(
                $usuario
            ),
        ];

        if ($sucursal === null) {
            return $resumen;
        }

        $usuario->load('role');

        $rol = $usuario->role?->slug;

        $alcanceCajaSucursal = in_array(
            $rol,
            ['administrador', 'encargado'],
            true
        );

        $alcanceFinancieroSucursal =
            $alcanceCajaSucursal && $usuario->can(
                'joysys.permiso',
                'reportes.ver'
            );

        if ($usuario->can('joysys.permiso', 'ventas.ver')) {
            $resumen['ventas'] = $this->obtenerVentas(
                $usuario,
                $sucursal,
                $inicioUtc,
                $finUtc,
                $alcanceFinancieroSucursal
            );

            $resumen['ventas_recientes'] =
                $this->obtenerVentasRecientes(
                    $usuario,
                    $sucursal,
                    $alcanceFinancieroSucursal
                );
        }

        if ($usuario->can('joysys.permiso', 'pagos.ver')) {
            $resumen['cobros'] = $this->obtenerCobros(
                $usuario,
                $sucursal,
                $inicioUtc,
                $finUtc,
                $alcanceFinancieroSucursal
            );
        }

        if ($usuario->can('joysys.permiso', 'inventario.ver')) {
            $resumen['inventario'] =
                $this->obtenerInventario($sucursal);
        }

        if ($usuario->can('joysys.permiso', 'incidencias.ver')) {
            $resumen['incidencias'] =
                $this->obtenerIncidencias($sucursal);
        }

        if ($usuario->can('joysys.permiso', 'caja.ver')) {
            $resumen['caja'] = $this->obtenerCaja(
                $usuario,
                $sucursal,
                $alcanceCajaSucursal
            );
        }

        return $resumen;
    }

    private function obtenerVentas(
        User $usuario,
        Sucursal $sucursal,
        CarbonImmutable $inicioUtc,
        CarbonImmutable $finUtc,
        bool $alcanceSucursal
    ): array {
        $consulta = DB::table('ventas')
            ->where('sucursal_id', $sucursal->id)
            ->where('estado', 'completada')
            ->where('created_at', '>=', $inicioUtc)
            ->where('created_at', '<', $finUtc);

        if (! $alcanceSucursal) {
            $consulta->where('usuario_id', $usuario->id);
        }

        $resultado = $consulta
            ->selectRaw(
                'COUNT(*) AS cantidad, COALESCE(SUM(total), 0.0000) AS importe'
            )
            ->first();

        return [
            'cantidad' => (int) $resultado->cantidad,
            'importe' => (string) $resultado->importe,
            'alcance' => $alcanceSucursal
                ? 'sucursal'
                : 'propio',
        ];
    }

    private function obtenerCobros(
        User $usuario,
        Sucursal $sucursal,
        CarbonImmutable $inicioUtc,
        CarbonImmutable $finUtc,
        bool $alcanceSucursal
    ): array {
        $consulta = DB::table('pagos as p')
            ->join(
                'detalle_pagos as dp',
                'dp.pago_id',
                '=',
                'p.id'
            )
            ->where('p.sucursal_id', $sucursal->id)
            ->where('p.estado', 'confirmado')
            ->where('p.moneda', 'MXN')
            ->whereNull('p.pago_original_id')
            ->whereNull('dp.detalle_pago_original_id')
            ->where('dp.metodo_pago', '!=', 'saldo_favor')
            ->where('p.fecha_pago', '>=', $inicioUtc)
            ->where('p.fecha_pago', '<', $finUtc);

        if (! $alcanceSucursal) {
            $consulta->where(
                'p.recibido_por',
                $usuario->id
            );
        }

        $resultado = $consulta
            ->selectRaw(
                'COALESCE(SUM(dp.monto), 0.0000) AS importe'
            )
            ->first();

        return [
            'importe' => (string) $resultado->importe,
            'alcance' => $alcanceSucursal
                ? 'sucursal'
                : 'propio',
        ];
    }

    private function obtenerInventario(
        Sucursal $sucursal
    ): array {
        $piezas = DB::table('piezas as pi')
            ->join(
                'productos as pr',
                'pr.id',
                '=',
                'pi.producto_id'
            )
            ->join(
                'estados_inventario as ei',
                'ei.id',
                '=',
                'pi.estado_inventario_id'
            )
            ->where('pi.sucursal_id', $sucursal->id)
            ->where('ei.codigo', 'disponible')
            ->where('pr.tipo', 'individual')
            ->where('pr.estado', 'activo')
            ->whereNull('pr.deleted_at')
            ->count();

        $existencias = DB::table('existencias as ex')
            ->join(
                'productos as pr',
                'pr.id',
                '=',
                'ex.producto_id'
            )
            ->join(
                'estados_inventario as ei',
                'ei.id',
                '=',
                'ex.estado_inventario_id'
            )
            ->where('ex.sucursal_id', $sucursal->id)
            ->where('ei.codigo', 'disponible')
            ->where('pr.estado', 'activo')
            ->whereNull('pr.deleted_at');

        $unidades = (clone $existencias)
            ->where('pr.tipo', 'por_cantidad')
            ->selectRaw(
                'COALESCE(SUM(ex.cantidad), 0.0000) AS cantidad'
            )
            ->first();

        $gramos = (clone $existencias)
            ->where('pr.tipo', 'por_peso')
            ->selectRaw(
                'COALESCE(SUM(ex.cantidad), 0.0000) AS cantidad'
            )
            ->first();

        return [
            'piezas' => (int) $piezas,
            'unidades' => (string) $unidades->cantidad,
            'gramos' => (string) $gramos->cantidad,
        ];
    }

    private function obtenerIncidencias(
        Sucursal $sucursal
    ): array {
        $consulta = DB::table('incidencias_inventario')
            ->where('sucursal_id', $sucursal->id)
            ->whereIn(
                'estado',
                ['abierta', 'en_revision']
            );

        $resultado = $consulta
            ->selectRaw(
                "COUNT(*) AS total,
                COALESCE(SUM(
                    CASE WHEN estado = 'abierta'
                    THEN 1 ELSE 0 END
                ), 0) AS abiertas,
                COALESCE(SUM(
                    CASE WHEN estado = 'en_revision'
                    THEN 1 ELSE 0 END
                ), 0) AS en_revision"
            )
            ->first();

        return [
            'total' => (int) $resultado->total,
            'abiertas' => (int) $resultado->abiertas,
            'en_revision' => (int) $resultado->en_revision,
        ];
    }

    private function obtenerCaja(
        User $usuario,
        Sucursal $sucursal,
        bool $alcanceSucursal
    ): array {
        $cajasActivas = DB::table('cajas')
            ->where('sucursal_id', $sucursal->id)
            ->where('estado', 'activa')
            ->count();

        $sesiones = DB::table('sesiones_caja as sc')
            ->join(
                'cajas as c',
                'c.id',
                '=',
                'sc.caja_id'
            )
            ->where('sc.sucursal_id', $sucursal->id)
            ->where('c.sucursal_id', $sucursal->id)
            ->where('sc.estado', 'abierta');

        $sesionPropia = (clone $sesiones)
            ->where('sc.usuario_id', $usuario->id)
            ->select(
                'sc.id',
                'sc.fecha_apertura',
                'c.nombre',
                'c.codigo',
                'c.estado as estado_caja'
            )
            ->first();

        $sesionesAbiertas = null;

        if ($alcanceSucursal) {
            $sesionesAbiertas = (clone $sesiones)->count();
        }

        return [
            'cajas_activas' => (int) $cajasActivas,
            'sesiones_abiertas' => $sesionesAbiertas,
            'sesion_propia' => $sesionPropia,
            'alcance' => $alcanceSucursal
                ? 'sucursal'
                : 'propio',
        ];
    }

    private function obtenerVentasRecientes(
        User $usuario,
        Sucursal $sucursal,
        bool $alcanceSucursal
    ): array {
        $consulta = DB::table('ventas')
            ->where('sucursal_id', $sucursal->id)
            ->where('estado', 'completada');

        if (! $alcanceSucursal) {
            $consulta->where('usuario_id', $usuario->id);
        }

        return $consulta
            ->select(
                'id',
                'folio',
                'total',
                'created_at'
            )
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(
                (int) config(
                    'dashboard.limite_ventas_recientes',
                    5
                )
            )
            ->get()
            ->all();
    }

    private function obtenerAccesosRapidos(
        User $usuario
    ): array {
        return collect(
            config('dashboard.accesos_rapidos', [])
        )
            ->filter(
                fn (array $acceso): bool =>
                    $usuario->can(
                        'joysys.permiso',
                        $acceso['permiso']
                    )
            )
            ->values()
            ->all();
    }
}