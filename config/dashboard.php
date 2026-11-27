<?php

return [

    'zona_horaria' => 'America/Mexico_City',

    'moneda' => 'MXN',

    'limite_ventas_recientes' => 5,

    'decimales_monetarios' => 2,

    'accesos_rapidos' => [

        [
            'nombre' => 'Nueva venta',
            'descripcion' => 'Registrar una nueva venta.',
            'permiso' => 'ventas.crear',
            'icono' => 'cart',
            'ruta' => null,
            'requiere_sucursal' => true,
        ],

        [
            'nombre' => 'Abrir caja',
            'descripcion' => 'Iniciar una sesión de caja.',
            'permiso' => 'caja.abrir',
            'icono' => 'wallet',
            'ruta' => null,
            'requiere_sucursal' => true,
        ],

        [
            'nombre' => 'Cerrar caja',
            'descripcion' => 'Finalizar una sesión de caja.',
            'permiso' => 'caja.cerrar',
            'icono' => 'wallet',
            'ruta' => null,
            'requiere_sucursal' => true,
        ],

        [
            'nombre' => 'Registrar producto',
            'descripcion' => 'Agregar un producto al catálogo.',
            'permiso' => 'catalogos.crear',
            'icono' => 'package',
            'ruta' => null,
            'requiere_sucursal' => false,
        ],

        [
            'nombre' => 'Registrar cliente',
            'descripcion' => 'Agregar un cliente.',
            'permiso' => 'clientes.crear',
            'icono' => 'contact',
            'ruta' => null,
            'requiere_sucursal' => false,
        ],

        [
            'nombre' => 'Reportar incidencia',
            'descripcion' => 'Registrar una incidencia de inventario.',
            'permiso' => 'incidencias.reportar',
            'icono' => 'alert',
            'ruta' => null,
            'requiere_sucursal' => true,
        ],

    ],

];