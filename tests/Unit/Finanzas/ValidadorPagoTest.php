<?php

namespace Tests\Unit\Finanzas;

use App\Services\Finanzas\ValidadorPago;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ValidadorPagoTest extends TestCase
{
    private function detalle(
        string $metodo = 'efectivo',
        mixed $monto = '100'
    ): array {
        return [
            'metodo_pago' => $metodo,
            'monto' => $monto,
        ];
    }

    public function test_moneda_mxn(): void
    {
        $this->assertSame(
            'MXN',
            ValidadorPago::validarMoneda('MXN')
        );
    }

    public function test_rechaza_moneda_incompatible(): void
    {
        $this->expectException(ValidationException::class);

        ValidadorPago::validarMoneda('USD');
    }

    public static function metodosValidos(): array
    {
        return [
            ['efectivo'],
            ['tarjeta_credito'],
            ['tarjeta_debito'],
            ['transferencia'],
            ['saldo_favor'],
            ['otro'],
        ];
    }

    #[DataProvider('metodosValidos')]
    public function test_reconoce_metodo(
        string $metodo
    ): void {
        $this->assertSame(
            $metodo,
            ValidadorPago::validarMetodo($metodo)
        );
    }

    public function test_rechaza_metodo_desconocido(): void
    {
        $this->expectException(ValidationException::class);

        ValidadorPago::validarMetodo('desconocido');
    }

    public function test_pago_simple(): void
    {
        $resultado = ValidadorPago::resumir([
            $this->detalle(),
        ], '100');

        $this->assertSame(
            '100.0000',
            $resultado['monto_total']
        );

        $this->assertSame(
            '100.0000',
            $resultado['efectivo']
        );

        $this->assertSame(
            '0.0000',
            $resultado['no_efectivo']
        );
    }

    public function test_pago_mixto(): void
    {
        $resultado = ValidadorPago::resumir([
            $this->detalle('efectivo', '300'),
            $this->detalle('tarjeta_debito', '500'),
            $this->detalle('transferencia', '200'),
        ], '1000');

        $this->assertSame(
            '1000.0000',
            $resultado['monto_total']
        );

        $this->assertSame(
            '300.0000',
            $resultado['efectivo']
        );

        $this->assertSame(
            '700.0000',
            $resultado['no_efectivo']
        );

        $this->assertSame(
            '500.0000',
            $resultado['por_metodo']['tarjeta_debito']
        );
    }

    public function test_metodos_repetidos(): void
    {
        $resultado = ValidadorPago::resumir([
            $this->detalle('efectivo', '50'),
            $this->detalle('efectivo', '25'),
            $this->detalle('transferencia', '25'),
        ], '100');

        $this->assertSame(
            '75.0000',
            $resultado['efectivo']
        );

        $this->assertCount(3, $resultado['detalles']);
    }

    public function test_pago_sin_efectivo(): void
    {
        $resultado = ValidadorPago::resumir([
            $this->detalle('tarjeta_credito', '100'),
        ], '100');

        $this->assertSame(
            '0.0000',
            $resultado['efectivo']
        );

        $this->assertSame(
            '100.0000',
            $resultado['no_efectivo']
        );
    }

    public function test_normaliza_textos(): void
    {
        $detalle = $this->detalle();

        $detalle['referencia'] = '  REF-123  ';
        $detalle['detalle_adicional'] = '   ';

        $resultado = ValidadorPago::validarDetalles([
            $detalle,
        ], '100');

        $this->assertSame(
            'REF-123',
            $resultado[0]['referencia']
        );

        $this->assertNull(
            $resultado[0]['detalle_adicional']
        );
    }

    public static function detallesInvalidos(): array
    {
        return [
            'vacio' => [[], '100'],

            'total_insuficiente' => [[
                [
                    'metodo_pago' => 'efectivo',
                    'monto' => '99.9999',
                ],
            ], '100'],

            'total_excedido' => [[
                [
                    'metodo_pago' => 'efectivo',
                    'monto' => '100.0001',
                ],
            ], '100'],

            'monto_cero' => [[
                [
                    'metodo_pago' => 'efectivo',
                    'monto' => '0',
                ],
            ], '100'],

            'monto_float' => [[
                [
                    'metodo_pago' => 'efectivo',
                    'monto' => 100.0,
                ],
            ], '100'],

            'campo_protegido' => [[
                [
                    'metodo_pago' => 'efectivo',
                    'monto' => '100',
                    'pago_id' => 1,
                ],
            ], '100'],

            'referencia_original' => [[
                [
                    'metodo_pago' => 'efectivo',
                    'monto' => '100',
                    'detalle_pago_original_id' => 1,
                ],
            ], '100'],

            'referencia_larga' => [[
                [
                    'metodo_pago' => 'efectivo',
                    'monto' => '100',
                    'referencia' => str_repeat('A', 151),
                ],
            ], '100'],

            'detalle_adicional_largo' => [[
                [
                    'metodo_pago' => 'efectivo',
                    'monto' => '100',
                    'detalle_adicional' => str_repeat('A', 256),
                ],
            ], '100'],

            'referencia_invalida' => [[
                [
                    'metodo_pago' => 'efectivo',
                    'monto' => '100',
                    'referencia' => [],
                ],
            ], '100'],

            'detalle_invalido' => [['invalido'], '100'],

            'metodo_faltante' => [[
                ['monto' => '100'],
            ], '100'],

            'monto_faltante' => [[
                ['metodo_pago' => 'efectivo'],
            ], '100'],

            'lista_asociativa' => [[
                'primero' => [
                    'metodo_pago' => 'efectivo',
                    'monto' => '100',
                ],
            ], '100'],
        ];
    }

    #[DataProvider('detallesInvalidos')]
    public function test_rechaza_detalles_invalidos(
        array $detalles,
        string $total
    ): void {
        $this->expectException(ValidationException::class);

        ValidadorPago::validarDetalles(
            $detalles,
            $total
        );
    }
}
