<?php

namespace Database\Seeders;

use App\Models\Permiso;
use Illuminate\Database\Seeder;

class PermisoSeeder extends Seeder
{
    public const PERMISOS = [
        'dashboard' => [
            'dashboard.ver' => 'Consultar dashboard',
        ],

        'usuarios' => [
            'usuarios.ver' => 'Consultar usuarios',
            'usuarios.crear' => 'Crear usuarios',
            'usuarios.editar' => 'Editar usuarios',
            'usuarios.cambiar_estado' => 'Cambiar estado de usuarios',
            'usuarios.restablecer_password' => 'Restablecer contrasenas',
        ],

        'roles' => [
            'roles.ver' => 'Consultar roles',
            'roles.asignar' => 'Asignar roles',
            'permisos.gestionar' => 'Gestionar permisos',
        ],

        'sucursales' => [
            'sucursales.ver' => 'Consultar sucursales',
            'sucursales.editar' => 'Editar sucursales',
            'sucursales.asignar_empleados' => 'Asignar empleados a sucursales',
        ],

        'catalogos' => [
            'catalogos.ver' => 'Consultar catalogos',
            'catalogos.crear' => 'Crear registros de catalogos',
            'catalogos.editar' => 'Editar registros de catalogos',
            'catalogos.cambiar_estado' => 'Cambiar estado de registros de catalogos',
        ],

        'inventario' => [
            'inventario.ver' => 'Consultar inventario',
            'inventario.registrar_entrada' => 'Registrar entradas de inventario',
            'inventario.registrar_salida' => 'Registrar salidas de inventario',
            'inventario.ajustar' => 'Realizar ajustes de inventario',
        ],

        'incidencias' => [
            'incidencias.ver' => 'Consultar incidencias',
            'incidencias.reportar' => 'Reportar incidencias',
            'incidencias.resolver' => 'Resolver incidencias',
        ],

        'precios' => [
            'precios.ver' => 'Consultar precios',
            'precios.modificar' => 'Modificar precios',
            'precios.ver_historial' => 'Consultar historial de precios',
        ],

        'clientes' => [
            'clientes.ver' => 'Consultar clientes',
            'clientes.crear' => 'Registrar clientes',
            'clientes.editar' => 'Editar clientes',
            'clientes.desactivar' => 'Desactivar clientes',
        ],

        'ventas' => [
            'ventas.ver' => 'Consultar ventas',
            'ventas.crear' => 'Registrar ventas',
            'ventas.descuento_basico' => 'Aplicar descuento basico',
            'ventas.descuento_superior' => 'Aplicar descuento superior',
        ],

        'pagos' => [
            'pagos.ver' => 'Consultar pagos',
            'pagos.registrar' => 'Registrar pagos',
            'pagos.reembolsar' => 'Autorizar reembolsos',
        ],

        'saldos_favor' => [
            'saldos_favor.generar' => 'Generar saldos a favor autorizados',
            'saldos_favor.cancelar' => 'Cancelar saldos a favor',
        ],

        'caja' => [
            'caja.ver' => 'Consultar caja',
            'caja.abrir' => 'Abrir sesion de caja',
            'caja.cerrar' => 'Cerrar sesion de caja',
            'caja.registrar_gasto' => 'Registrar gastos de caja',
            'caja.registrar_retiro' => 'Registrar retiros de caja',
            'caja.autorizar_compensacion' => 'Autorizar compensaciones de caja',
        ],

        'reportes' => [
            'reportes.ver' => 'Consultar reportes',
        ],

        'configuracion' => [
            'configuracion.gestionar' => 'Gestionar configuracion del sistema',
        ],
    ];

    public function run(): void
    {
        foreach (self::PERMISOS as $modulo => $permisos) {
            foreach ($permisos as $clave => $nombre) {
                Permiso::firstOrCreate(
                    [
                        'clave' => $clave,
                    ],
                    [
                        'nombre' => $nombre,
                        'modulo' => $modulo,
                        'descripcion' => null,
                    ]
                );
            }
        }
    }
}