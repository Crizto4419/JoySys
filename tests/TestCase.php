<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected bool $transaccionIniciada = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->verificarEntornoDePruebas();

        DB::beginTransaction();

        $this->transaccionIniciada = true;
    }

    protected function verificarEntornoDePruebas(): void
    {
        if (!app()->environment('testing')) {
            throw new RuntimeException(
                'Entorno inseguro: las pruebas requieren APP_ENV=testing.'
            );
        }

        $configuracionEsperada = [
            'database.default' => 'mysql',
            'database.connections.mysql.database' => 'joysys_testing',
            'database.connections.mysql.username' => 'joysys_test_user',
            'cache.default' => 'array',
            'session.driver' => 'array',
            'queue.default' => 'sync',
            'mail.default' => 'array',
        ];

        foreach ($configuracionEsperada as $clave => $valor) {
            if (config($clave) !== $valor) {
                throw new RuntimeException(
                    "Configuración insegura para pruebas: {$clave}."
                );
            }
        }

        if (!empty(config('database.connections.mysql.url'))) {
            throw new RuntimeException(
                'La conexión de pruebas no debe utilizar DB_URL.'
            );
        }

        $conexion = DB::selectOne(
            'SELECT DATABASE() AS nombre, CURRENT_USER() AS usuario'
        );

        if (
            $conexion === null ||
            $conexion->nombre !== 'joysys_testing'
        ) {
            throw new RuntimeException(
                'Conexión insegura: base de datos no autorizada.'
            );
        }

        if ($conexion->usuario !== 'joysys_test_user@localhost') {
            throw new RuntimeException(
                'Conexión insegura: usuario de MySQL no autorizado.'
            );
        }
    }

    protected function tearDown(): void
    {
        try {
            if ($this->transaccionIniciada) {
                if (DB::transactionLevel() > 0) {
                    DB::rollBack(0);
                }

                $this->transaccionIniciada = false;
            }
        } finally {
            parent::tearDown();
        }
    }
}