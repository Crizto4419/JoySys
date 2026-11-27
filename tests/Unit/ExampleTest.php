<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\Auth\AutorizacionService;
use App\Services\Auth\ContextoSucursalService;
use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    public function test_autorizacion_rechaza_usuario_no_persistido(): void
    {
        $usuario = new User();

        $servicio = new AutorizacionService();

        $this->assertFalse(
            $servicio->tienePermiso(
                $usuario,
                'dashboard.ver'
            )
        );
    }

    public function test_autorizacion_rechaza_permiso_vacio(): void
    {
        $usuario = new User();

        $servicio = new AutorizacionService();

        $this->assertFalse(
            $servicio->tienePermiso($usuario, '')
        );
    }

    public function test_autorizacion_rechaza_permiso_con_espacios(): void
    {
        $usuario = new User();

        $servicio = new AutorizacionService();

        $this->assertFalse(
            $servicio->tienePermiso($usuario, '   ')
        );
    }

    public function test_contexto_rechaza_usuario_no_persistido(): void
    {
        $usuario = new User();

        $servicio = new ContextoSucursalService();

        $this->assertNull(
            $servicio->obtenerSucursal($usuario)
        );

        $this->assertNull(
            $servicio->obtenerSucursalId($usuario)
        );

        $this->assertFalse(
            $servicio->tieneSucursalActiva($usuario)
        );
    }

    public function test_contexto_rechaza_identificadores_invalidos(): void
    {
        $usuario = new User();

        $servicio = new ContextoSucursalService();

        $this->assertFalse(
            $servicio->puedeOperarEnSucursal($usuario, 0)
        );

        $this->assertFalse(
            $servicio->puedeOperarEnSucursal($usuario, -1)
        );
    }
}