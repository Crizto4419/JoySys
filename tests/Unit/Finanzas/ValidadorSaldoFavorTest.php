<?php

namespace Tests\Unit\Finanzas;

use App\Services\Finanzas\ValidadorSaldoFavor;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class ValidadorSaldoFavorTest extends TestCase
{
    public function test_sf01_generacion(): void
    {
        $r = ValidadorSaldoFavor::calcular(
            'generacion', 'activo', '0', '500.1234'
        );

        $this->assertSame('500.1234', $r['saldo_nuevo']);
        $this->assertSame('activo', $r['estado_nuevo']);
    }

    public function test_sf02_aplicacion_parcial(): void
    {
        $r = ValidadorSaldoFavor::calcular(
            'aplicacion', 'activo', '500', '125.1234'
        );

        $this->assertSame('374.8766', $r['saldo_nuevo']);
    }

    public function test_sf03_aplicacion_total(): void
    {
        $r = ValidadorSaldoFavor::calcular(
            'aplicacion', 'activo', '500', '500'
        );

        $this->assertSame('0.0000', $r['saldo_nuevo']);
        $this->assertSame('agotado', $r['estado_nuevo']);
    }

    public function test_sf04_restitucion(): void
    {
        $r = ValidadorSaldoFavor::calcular(
            'restitucion', 'activo', '50', '25.1234'
        );

        $this->assertSame('75.1234', $r['saldo_nuevo']);
    }

    public function test_sf05_restitucion_agotado(): void
    {
        $r = ValidadorSaldoFavor::calcular(
            'restitucion', 'agotado', '0', '100'
        );

        $this->assertSame('100.0000', $r['saldo_nuevo']);
        $this->assertSame('activo', $r['estado_nuevo']);
    }

    public function test_sf06_cancelacion(): void
    {
        $r = ValidadorSaldoFavor::calcular(
            'cancelacion', 'activo', '100', '100'
        );

        $this->assertSame('cancelado', $r['estado_nuevo']);
        $this->assertSame('0.0000', $r['saldo_nuevo']);
    }

    public function test_sf07_rechaza_exceso(): void
    {
        $this->expectException(ValidationException::class);

        ValidadorSaldoFavor::calcular(
            'aplicacion', 'activo', '100', '100.0001'
        );
    }

    public function test_sf08_rechaza_cancelacion_parcial(): void
    {
        $this->expectException(ValidationException::class);

        ValidadorSaldoFavor::calcular(
            'cancelacion', 'activo', '100', '50'
        );
    }

    public function test_sf09_rechaza_credito_cancelado(): void
    {
        $this->expectException(ValidationException::class);

        ValidadorSaldoFavor::calcular(
            'restitucion', 'cancelado', '0', '50'
        );
    }

    public function test_sf10_rechaza_desbordamiento(): void
    {
        $this->expectException(ValidationException::class);

        ValidadorSaldoFavor::calcular(
            'restitucion', 'activo', '99999999.9999', '0.0001'
        );
    }

    public function test_sf11_rechaza_estado_inconsistente(): void
    {
        $this->expectException(ValidationException::class);

        ValidadorSaldoFavor::validarEstado('agotado', '100');
    }

    public function test_sf12_rechaza_generacion_sobre_saldo(): void
    {
        $this->expectException(ValidationException::class);

        ValidadorSaldoFavor::calcular(
            'generacion', 'activo', '100', '50'
        );
    }

    public function test_sf13_rechaza_tipo_invalido(): void
    {
        $this->expectException(ValidationException::class);

        ValidadorSaldoFavor::calcular(
            'ajuste', 'activo', '100', '50'
        );
    }

    public function test_sf14_rechaza_monto_cero(): void
    {
        $this->expectException(ValidationException::class);

        ValidadorSaldoFavor::calcular(
            'aplicacion', 'activo', '100', '0'
        );
    }

    public function test_sf15_rechaza_estado_invalido(): void
    {
        $this->expectException(ValidationException::class);

        ValidadorSaldoFavor::validarEstado('invalido', '0');
    }
}