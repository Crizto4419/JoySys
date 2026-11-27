<?php

return [

    'nombre' => 'JoySys',

    'version' => 'MVP 1.0',

    'navegacion' => [

        [
            'nombre' => 'Catálogos',
            'permiso' => 'catalogos.ver',
            'descripcion' => 'Consulta y gestión de catálogos generales.',
            'icono' => 'package',
            'grupo' => 'operaciones',
            'ruta' => 'catalogos.index',
            'requiere_sucursal' => false,
        ],

        [
            'nombre' => 'Inventario',
            'permiso' => 'inventario.ver',
            'descripcion' => 'Control de productos, piezas y existencias.',
            'icono' => 'boxes',
            'grupo' => 'operaciones',
            'ruta' => 'inventario.index',
            'requiere_sucursal' => true,
        ],

        [
            'nombre' => 'Incidencias',
            'permiso' => 'incidencias.ver',
            'descripcion' => 'Registro y seguimiento de incidencias de inventario.',
            'icono' => 'alert',
            'grupo' => 'operaciones',
            'ruta' => 'incidencias.index',
            'requiere_sucursal' => true,
        ],

        [
            'nombre' => 'Precios',
            'permiso' => 'precios.ver',
            'descripcion' => 'Consulta y administración de precios de productos.',
            'icono' => 'tag',
            'grupo' => 'operaciones',
            'ruta' => 'precios.index',
            'requiere_sucursal' => false,
        ],

        [
            'nombre' => 'Clientes',
            'permiso' => 'clientes.ver',
            'descripcion' => 'Registro y consulta de clientes.',
            'icono' => 'contact',
            'grupo' => 'operaciones',
            'ruta' => 'clientes.index',
            'requiere_sucursal' => false,
        ],

        [
            'nombre' => 'Ventas',
            'permiso' => 'ventas.ver',
            'descripcion' => 'Registro y consulta de operaciones de venta.',
            'icono' => 'cart',
            'grupo' => 'operaciones',
            'ruta' => null,
            'requiere_sucursal' => true,
        ],

        [
            'nombre' => 'Pagos',
            'permiso' => 'pagos.ver',
            'descripcion' => 'Registro y seguimiento de pagos.',
            'icono' => 'credit-card',
            'grupo' => 'operaciones',
            'ruta' => null,
            'requiere_sucursal' => true,
        ],

        [
            'nombre' => 'Caja',
            'permiso' => 'caja.ver',
            'descripcion' => 'Control de sesiones y movimientos de caja.',
            'icono' => 'wallet',
            'grupo' => 'operaciones',
            'ruta' => 'caja.index',
            'requiere_sucursal' => true,
        ],

        [
            'nombre' => 'Reportes',
            'permiso' => 'reportes.ver',
            'descripcion' => 'Consulta de información y resultados operativos.',
            'icono' => 'chart',
            'grupo' => 'operaciones',
            'ruta' => null,
            'requiere_sucursal' => true,
        ],

        [
            'nombre' => 'Sucursales',
            'permiso' => 'sucursales.ver',
            'descripcion' => 'Consulta y administración de sucursales.',
            'icono' => 'store',
            'grupo' => 'administracion',
            'ruta' => null,
            'requiere_sucursal' => false,
        ],

        [
            'nombre' => 'Usuarios',
            'permiso' => 'usuarios.ver',
            'descripcion' => 'Administración de usuarios del sistema.',
            'icono' => 'users',
            'grupo' => 'administracion',
            'ruta' => null,
            'requiere_sucursal' => false,
        ],

        [
            'nombre' => 'Roles y permisos',
            'permiso' => 'roles.ver',
            'descripcion' => 'Administración de roles y autorizaciones.',
            'icono' => 'shield',
            'grupo' => 'administracion',
            'ruta' => null,
            'requiere_sucursal' => false,
        ],

        [
            'nombre' => 'Configuración',
            'permiso' => 'configuracion.gestionar',
            'descripcion' => 'Administración de parámetros del sistema.',
            'icono' => 'settings',
            'grupo' => 'administracion',
            'ruta' => null,
            'requiere_sucursal' => false,
        ],

    ],

];
