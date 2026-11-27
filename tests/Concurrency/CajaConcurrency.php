<?php

use App\Models\User;
use App\Models\Caja;
use App\Models\SesionCaja;
use App\Models\MovimientoCaja;
use App\Models\Pago;
use App\Models\DetallePago;
use App\Services\Caja\SesionCajaService;
use App\Services\Caja\MovimientoCajaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

require dirname(__DIR__, 2).'/vendor/autoload.php';

function comprobar(bool $condicion, string $mensaje): void
{
    if (!$condicion) {
        throw new RuntimeException($mensaje);
    }
}

function importe(mixed $valor, string $esperado): void
{
    comprobar(
        bccomp((string) $valor, $esperado, 4) === 0,
        "Importe incorrecto: {$valor}; esperado: {$esperado}"
    );
}

function iniciar(): void
{
    $app = require dirname(__DIR__, 2).'/bootstrap/app.php';

    $app->make(
        \Illuminate\Contracts\Console\Kernel::class
    )->bootstrap();

    $identidad = DB::selectOne(
        'SELECT DATABASE() AS base,
                CURRENT_USER() AS usuario,
                @@GLOBAL.log_bin_trust_function_creators AS seguridad'
    );

    comprobar(
        app()->environment('testing')
        && DB::connection()->getDriverName() === 'mysql'
        && $identidad->base === 'joysys_concurrencia_testing'
        && $identidad->usuario === 'joysys_test_user@localhost'
        && (int) $identidad->seguridad === 0,
        'Entorno de ejecucion incorrecto.'
    );
}

function prepararUsuario(int $sucursal): User
{
    $rol = DB::table('roles')
        ->where('slug', 'administrador')
        ->value('id');

    if ($rol === null) {
        $rol = DB::table('roles')->insertGetId([
            'nombre' => 'Administrador',
            'slug' => 'administrador',
            'estado' => 'activo',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $permisos = [
        'caja.ver',
        'caja.abrir',
        'caja.cerrar',
        'caja.registrar_gasto',
        'caja.registrar_retiro',
        'caja.autorizar_compensacion',
        'pagos.ver',
        'pagos.registrar',
    ];

    foreach ($permisos as $clave) {
        $permiso = DB::table('permisos')
            ->where('clave', $clave)
            ->value('id');

        if ($permiso === null) {
            $permiso = DB::table('permisos')->insertGetId([
                'clave' => $clave,
                'nombre' => $clave,
                'modulo' => explode('.', $clave)[0],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('rol_permiso')->insertOrIgnore([
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
        'usuario_id' => $usuario->id,
        'sucursal_id' => $sucursal,
        'es_principal' => true,
        'fecha_inicio' => now()->subDay()->toDateString(),
        'fecha_fin' => null,
        'estado' => 'activo',
        'asignado_por' => $usuario->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $usuario;
}

function escenario(string $nombre): array
{
    $codigo = $nombre.'-'.bin2hex(random_bytes(4));

    $sucursal = DB::table('sucursales')->insertGetId([
        'codigo' => $codigo,
        'nombre' => $codigo,
        'estado' => 'activa',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $usuario = prepararUsuario($sucursal);
    $otro = prepararUsuario($sucursal);

    $cajas = [];

    foreach ([1, 2] as $numero) {
        $cajas[] = Caja::query()->create([
            'sucursal_id' => $sucursal,
            'codigo' => 'CAJA-'.$numero,
            'nombre' => 'Caja '.$numero,
            'moneda' => 'MXN',
            'estado' => 'activa',
        ]);
    }

    return [$sucursal, $usuario, $otro, $cajas[0], $cajas[1]];
}

function abrir(User $usuario, Caja $caja): SesionCaja
{
    return app(SesionCajaService::class)->abrir([
        'caja_id' => $caja->id,
        'fondo_inicial' => '100.0000',
    ], $usuario);
}

function datos(string $monto): array
{
    return [
        'monto' => $monto,
        'concepto' => 'Prueba de concurrencia',
    ];
}

function saldo(SesionCaja $sesion): string
{
    return app(MovimientoCajaService::class)->calcularSaldo(
        $sesion->fondo_inicial,
        $sesion->movimientos()
            ->with('movimientoOriginal')
            ->get()
    );
}

function ejecutarOperacion(array $tarea): mixed
{
    $usuario = User::query()->findOrFail($tarea['usuario']);

    $sesiones = app(SesionCajaService::class);
    $movimientos = app(MovimientoCajaService::class);

    return match ($tarea['operacion']) {
        'abrir' => $sesiones->abrir([
            'caja_id' => $tarea['caja'],
            'fondo_inicial' => '100.0000',
        ], $usuario),

        'gasto' => $movimientos->registrarGasto(
            datos($tarea['monto']),
            $usuario,
            $tarea['sesion']
        ),

        'retiro' => $movimientos->registrarRetiro(
            datos($tarea['monto']),
            $usuario,
            $tarea['sesion']
        ),

        'cerrar' => $sesiones->cerrarIdentificada(
            $tarea['sesion'],
            ['efectivo_contado' => '100.0000'],
            $usuario
        ),

        'pago' => $movimientos->registrarMovimientoPago(
            DetallePago::query()->findOrFail($tarea['detalle']),
            $usuario
        ),

        'compensar' => $movimientos->registrarCompensacion(
            MovimientoCaja::query()->findOrFail($tarea['original']),
            [
                'monto' => $tarea['monto'],
                'concepto' => 'Compensacion de concurrencia',
                'observaciones' => 'Efectivo reintegrado en prueba',
            ],
            $usuario
        ),

        default => throw new RuntimeException(
            'Operacion desconocida.'
        ),
    };
}

function trabajador(array $tarea, string $archivo): void
{
    $conexion = (int) DB::selectOne(
        'SELECT CONNECTION_ID() AS id'
    )->id;

    comprobar(
        file_put_contents($archivo, (string) $conexion) !== false,
        'No se pudo notificar disponibilidad.'
    );

    try {
        $resultado = ejecutarOperacion($tarea);

        echo json_encode([
            'estado' => 'correcto',
            'id' => $resultado?->id,
            'conexion' => $conexion,
        ], JSON_THROW_ON_ERROR);

    } catch (ValidationException $e) {
        echo json_encode([
            'estado' => 'rechazado',
            'errores' => $e->errors(),
            'conexion' => $conexion,
        ], JSON_THROW_ON_ERROR);
    }
}

function simultaneas(array $a, array $b): array
{
    $db = DB::connection();

    $nombre = 'joysys:caja:'.sha1($db->getDatabaseName());

    $principal = (int) $db->selectOne(
        'SELECT CONNECTION_ID() AS id',
        [],
        false
    )->id;

    $archivos = [];
    $procesos = [];
    $bloqueado = false;

    try {
        $bloqueo = $db->selectOne(
            'SELECT GET_LOCK(?, 0) AS adquirido',
            [$nombre],
            false
        );

        comprobar(
            (int) $bloqueo->adquirido === 1,
            'No se obtuvo el bloqueo de coordinacion.'
        );

        $bloqueado = true;

        foreach ([$a, $b] as $tarea) {
            $archivo = sys_get_temp_dir()
                .DIRECTORY_SEPARATOR
                .'joysys_cc_'.bin2hex(random_bytes(12));

            $archivos[] = $archivo;

            $proceso = new Process([
                PHP_BINARY,
                __FILE__,
                'trabajador',
                base64_encode(
                    json_encode($tarea, JSON_THROW_ON_ERROR)
                ),
                $archivo,
            ], dirname(__DIR__, 2));

            $proceso->setTimeout(30);
            $proceso->start();

            $procesos[] = $proceso;
        }

        $limite = microtime(true) + 6;

        do {
            $listos = true;

            foreach ($archivos as $archivo) {
                clearstatcache(true, $archivo);

                if (!is_file($archivo) || filesize($archivo) === 0) {
                    $listos = false;
                }
            }

            if (!$listos) {
                foreach ($procesos as $proceso) {
                    comprobar(
                        $proceso->isRunning(),
                        'Un proceso termino antes de estar listo: '
                        .$proceso->getErrorOutput()
                    );
                }

                usleep(20000);
            }

        } while (!$listos && microtime(true) < $limite);

        comprobar($listos, 'Tiempo de sincronizacion agotado.');

        $conexiones = array_map(
            fn ($archivo) => (int) file_get_contents($archivo),
            $archivos
        );

        comprobar(
            count(array_unique([
                $principal,
                ...$conexiones,
            ])) === 3,
            'Las conexiones MySQL no son independientes.'
        );

        usleep(200000);

        foreach ($procesos as $proceso) {
            comprobar(
                $proceso->isRunning(),
                'Un proceso termino antes de liberar el bloqueo.'
            );
        }

        echo "CONEXIONES: {$principal}, "
            .implode(', ', $conexiones).PHP_EOL;

        $liberado = $db->selectOne(
            'SELECT RELEASE_LOCK(?) AS liberado',
            [$nombre],
            false
        );

        comprobar(
            (int) $liberado->liberado === 1,
            'No se libero el bloqueo inicial.'
        );

        $bloqueado = false;

        $resultados = [];

        foreach ($procesos as $indice => $proceso) {
            $proceso->wait();

            comprobar(
                $proceso->isSuccessful(),
                'Fallo del proceso: '
                .$proceso->getErrorOutput()
                .$proceso->getOutput()
            );

            $resultado = json_decode(
                trim($proceso->getOutput()),
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            comprobar(
                $resultado['conexion'] === $conexiones[$indice],
                'Conexion inesperada en trabajador.'
            );

            echo 'TRABAJADOR '.($indice + 1).': '
                .json_encode($resultado).PHP_EOL;

            $resultados[] = $resultado;
        }

        $libre = $db->selectOne(
            'SELECT IS_FREE_LOCK(?) AS libre',
            [$nombre],
            false
        );

        comprobar(
            (int) $libre->libre === 1,
            'El bloqueo financiero no fue liberado.'
        );

        echo "BLOQUEO LIBERADO\n";

        return $resultados;

    } finally {
        if ($bloqueado) {
            $db->selectOne(
                'SELECT RELEASE_LOCK(?)',
                [$nombre],
                false
            );
        }

        foreach ($procesos as $proceso) {
            if ($proceso->isRunning()) {
                $proceso->stop(1);
            }
        }

        foreach ($archivos as $archivo) {
            if (is_file($archivo)) {
                unlink($archivo);
            }
        }
    }
}

function verificarResultados(array $resultados, int $correctos): void
{
    $total = count(array_filter(
        $resultados,
        fn ($r) => $r['estado'] === 'correcto'
    ));

    comprobar(
        $total === $correctos,
        'Cantidad inesperada de operaciones correctas.'
    );

    comprobar(
        count(array_filter(
            $resultados,
            fn ($r) => $r['estado'] === 'rechazado'
        )) === 2 - $correctos,
        'Cantidad inesperada de operaciones rechazadas.'
    );
}

try {
    iniciar();

    if (($argv[1] ?? '') === 'trabajador') {
        trabajador(
            json_decode(
                base64_decode($argv[2], true),
                true,
                512,
                JSON_THROW_ON_ERROR
            ),
            $argv[3]
        );

        exit(0);
    }

    comprobar(
        DB::table('migrations')->count() === 30,
        'Migraciones incorrectas.'
    );

    echo "REGISTROS PREVIOS CONSERVADOS\n";
    echo "USUARIOS INICIALES: ".DB::table('users')->count().PHP_EOL;
    echo "SESIONES INICIALES: ".DB::table('sesiones_caja')->count().PHP_EOL;
    echo "MOVIMIENTOS INICIALES: ".DB::table('movimientos_caja')->count().PHP_EOL;

    $aprobadas = 0;

    echo "\n=== CC01: MISMA CAJA ===\n";

    [$s, $u, $v, $c1, $c2] = escenario('CC01');

    $r = simultaneas(
        ['operacion' => 'abrir', 'usuario' => $u->id, 'caja' => $c1->id],
        ['operacion' => 'abrir', 'usuario' => $v->id, 'caja' => $c1->id]
    );

    verificarResultados($r, 1);

    comprobar(
        SesionCaja::query()
            ->where('caja_id', $c1->id)
            ->where('estado', 'abierta')
            ->count() === 1,
        'Se duplico la apertura de caja.'
    );

    echo "CC01 APROBADA\n";
    $aprobadas++;

    echo "\n=== CC02: MISMO EMPLEADO ===\n";

    [$s, $u, $v, $c1, $c2] = escenario('CC02');

    $r = simultaneas(
        ['operacion' => 'abrir', 'usuario' => $u->id, 'caja' => $c1->id],
        ['operacion' => 'abrir', 'usuario' => $u->id, 'caja' => $c2->id]
    );

    verificarResultados($r, 1);

    comprobar(
        SesionCaja::query()
            ->where('usuario_id', $u->id)
            ->where('estado', 'abierta')
            ->count() === 1,
        'El empleado tiene sesiones duplicadas.'
    );

    echo "CC02 APROBADA\n";
    $aprobadas++;

    foreach (['CC03-A' => 'gasto', 'CC03-B' => 'retiro'] as $caso => $tipo) {
        echo "\n=== {$caso}: SALIDAS SIMULTANEAS ===\n";

        [$s, $u, $v, $c1, $c2] = escenario($caso);

        $sesion = abrir($u, $c1);

        $tarea = [
            'operacion' => $tipo,
            'usuario' => $u->id,
            'sesion' => $sesion->id,
            'monto' => '80.0000',
        ];

        $r = simultaneas($tarea, $tarea);

        verificarResultados($r, 1);

        comprobar(
            $sesion->movimientos()->count() === 1,
            'Cantidad incorrecta de movimientos.'
        );

        comprobar(
            $sesion->movimientos()
                ->where('tipo_movimiento', $tipo)
                ->count() === 1,
            'Tipo de movimiento incorrecto.'
        );

        importe(saldo($sesion), '20.0000');

        echo "{$caso} APROBADA\n";
        $aprobadas++;
    }

    echo "\n=== CC04: GASTO CONTRA CIERRE ===\n";

    [$s, $u, $v, $c1, $c2] = escenario('CC04');

    $sesion = abrir($u, $c1);

    $r = simultaneas(
        [
            'operacion' => 'gasto',
            'usuario' => $u->id,
            'sesion' => $sesion->id,
            'monto' => '40.0000',
        ],
        [
            'operacion' => 'cerrar',
            'usuario' => $u->id,
            'sesion' => $sesion->id,
        ]
    );

    $sesion->refresh();

    comprobar(
        $r[1]['estado'] === 'correcto'
        && $sesion->estado === 'cerrada',
        'El cierre no se completo.'
    );

    importe($sesion->efectivo_contado, '100.0000');

    if ($r[0]['estado'] === 'correcto') {
        verificarResultados($r, 2);

        comprobar(
            $sesion->movimientos()->count() === 1,
            'Gasto no registrado correctamente.'
        );

        importe($sesion->saldo_esperado, '60.0000');
        importe($sesion->diferencia, '40.0000');

    } else {
        verificarResultados($r, 1);

        comprobar(
            $sesion->movimientos()->count() === 0,
            'Se registro un gasto incompatible con el cierre.'
        );

        importe($sesion->saldo_esperado, '100.0000');
        importe($sesion->diferencia, '0.0000');
    }

    importe(saldo($sesion), (string) $sesion->saldo_esperado);

    echo "CC04 APROBADA\n";
    $aprobadas++;

    echo "\n=== CC05: DOBLE CIERRE ===\n";

    [$s, $u, $v, $c1, $c2] = escenario('CC05');

    $sesion = abrir($u, $c1);

    $tarea = [
        'operacion' => 'cerrar',
        'usuario' => $u->id,
        'sesion' => $sesion->id,
    ];

    $r = simultaneas($tarea, $tarea);

    verificarResultados($r, 2);

    comprobar(
        $r[0]['id'] === $r[1]['id']
        && (int) $r[0]['id'] === (int) $sesion->id,
        'El cierre no es idempotente.'
    );

    $sesion->refresh();

    comprobar(
        $sesion->estado === 'cerrada'
        && $sesion->fecha_cierre !== null
        && (int) $sesion->cerrada_por === (int) $u->id,
        'Datos de cierre incorrectos.'
    );

    importe($sesion->saldo_esperado, '100.0000');
    importe($sesion->efectivo_contado, '100.0000');
    importe($sesion->diferencia, '0.0000');

    comprobar(
        $sesion->movimientos()->count() === 0,
        'El cierre genero movimientos inesperados.'
    );

    echo "CC05 APROBADA\n";
    $aprobadas++;

    echo "\n=== CC06: PAGO DUPLICADO ===\n";

    [$s, $u, $v, $c1, $c2] = escenario('CC06');

    $sesion = abrir($u, $c1);

    $pago = Pago::query()->create([
        'sucursal_id' => $s,
        'referencia_tipo' => 'venta',
        'referencia_id' => 1,
        'pago_original_id' => null,
        'recibido_por' => $u->id,
        'monto_total' => '50.0000',
        'moneda' => 'MXN',
        'estado' => 'confirmado',
        'fecha_pago' => now(),
    ]);

    $detalle = $pago->detalles()->create([
        'metodo_pago' => 'efectivo',
        'monto' => '50.0000',
        'referencia' => null,
        'detalle_adicional' => null,
        'detalle_pago_original_id' => null,
    ]);

    $tarea = [
        'operacion' => 'pago',
        'usuario' => $u->id,
        'detalle' => $detalle->id,
    ];

    $r = simultaneas($tarea, $tarea);

    verificarResultados($r, 2);

    comprobar(
        $r[0]['id'] !== null
        && $r[0]['id'] === $r[1]['id'],
        'El registro de pago no es idempotente.'
    );

    comprobar(
        MovimientoCaja::query()
            ->where('detalle_pago_id', $detalle->id)
            ->count() === 1,
        'Movimiento financiero duplicado.'
    );

    comprobar(
        $sesion->movimientos()
            ->where('tipo_movimiento', 'ingreso')
            ->count() === 1,
        'Ingreso incorrecto.'
    );

    importe(saldo($sesion), '150.0000');

    echo "CC06 APROBADA\n";
    $aprobadas++;

    echo "\n=== CC07: COMPENSACIONES SIMULTANEAS ===\n";

    [$s, $u, $v, $c1, $c2] = escenario('CC07');

    $sesion = abrir($u, $c1);

    $original = app(MovimientoCajaService::class)
        ->registrarGasto(
            datos('80.0000'),
            $u,
            $sesion->id
        );

    $tarea = [
        'operacion' => 'compensar',
        'usuario' => $u->id,
        'original' => $original->id,
        'monto' => '60.0000',
    ];

    $r = simultaneas($tarea, $tarea);

    verificarResultados($r, 1);

    comprobar(
        $original->compensaciones()->count() === 1,
        'Cantidad incorrecta de compensaciones.'
    );

    importe(
        $original->compensaciones()->sum('monto'),
        '60.0000'
    );

    importe($original->fresh()->monto, '80.0000');

    comprobar(
        $sesion->movimientos()->count() === 2,
        'Historial financiero incorrecto.'
    );

    importe(saldo($sesion), '80.0000');

    echo "CC07 APROBADA\n";
    $aprobadas++;

    echo "\n=== RESULTADO GENERAL ===\n";
    echo "PRUEBAS APROBADAS: {$aprobadas}/8\n";
    echo "USUARIOS: ".DB::table('users')->count().PHP_EOL;
    echo "SUCURSALES: ".DB::table('sucursales')->count().PHP_EOL;
    echo "CAJAS: ".DB::table('cajas')->count().PHP_EOL;
    echo "SESIONES: ".DB::table('sesiones_caja')->count().PHP_EOL;
    echo "MOVIMIENTOS: ".DB::table('movimientos_caja')->count().PHP_EOL;

    comprobar($aprobadas === 8, 'Cobertura incompleta.');

    echo "JOYSYS_CONCURRENCIA_8_DE_8_OK\n";

    exit(0);

} catch (\Throwable $e) {
    fwrite(
        STDERR,
        get_class($e).': '.$e->getMessage().PHP_EOL
        .$e->getTraceAsString().PHP_EOL
    );

    exit(1);
}