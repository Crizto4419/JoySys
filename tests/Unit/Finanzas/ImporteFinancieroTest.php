<?php

namespace Tests\Unit\Finanzas;

use App\Services\Finanzas\ImporteFinanciero;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ImporteFinancieroTest extends TestCase
{
    public function test_normaliza_enteros(): void
    {
        $this->assertSame(
            '100.0000',
            ImporteFinanciero::normalizar(100)
        );
    }

    public function test_normaliza_decimales(): void
    {
        $this->assertSame(
            '12.3400',
            ImporteFinanciero::normalizar('12.34')
        );
    }

    public function test_acepta_precision_maxima(): void
    {
        $this->assertSame(
            '0.0001',
            ImporteFinanciero::normalizar('0.0001')
        );
    }

    public function test_acepta_limite_monetario(): void
    {
        $this->assertSame(
            '99999999.9999',
            ImporteFinanciero::normalizar('99999999.9999')
        );
    }

    public function test_acepta_cero_autorizado(): void
    {
        $this->assertSame(
            '0.0000',
            ImporteFinanciero::normalizar('0', true)
        );
    }

    public function test_suma_exacta(): void
    {
        $this->assertSame(
            '0.3000',
            ImporteFinanciero::sumar(['0.1', '0.2'])
        );
    }

    public function test_suma_varios_importes(): void
    {
        $this->assertSame(
            '1000.0000',
            ImporteFinanciero::sumar([
                '300.1234',
                '200.8766',
                '499',
            ])
        );
    }

    public function test_suma_vacia(): void
    {
        $this->assertSame(
            '0.0000',
            ImporteFinanciero::sumar([])
        );
    }

    public function test_resta_exacta(): void
    {
        $this->assertSame(
            '74.9999',
            ImporteFinanciero::restar('100', '25.0001')
        );
    }

    public function test_resta_hasta_cero(): void
    {
        $this->assertSame(
            '0.0000',
            ImporteFinanciero::restar('50', '50')
        );
    }

    public function test_compara_importes(): void
    {
        $this->assertTrue(
            ImporteFinanciero::iguales('100', '100.0000')
        );

        $this->assertFalse(
            ImporteFinanciero::iguales('100', '100.0001')
        );
    }

    public function test_rechaza_desbordamiento(): void
    {
        $this->expectException(ValidationException::class);

        ImporteFinanciero::sumar([
            '99999999.9999',
            '0.0001',
        ]);
    }

    public function test_rechaza_resta_negativa(): void
    {
        $this->expectException(ValidationException::class);

        ImporteFinanciero::restar('10', '10.0001');
    }

    public static function importesInvalidos(): array
    {
        return [
            'cero' => ['0'],
            'negativo' => ['-10'],
            'exceso_precision' => ['1.00001'],
            'exceso_entero' => ['100000000'],
            'notacion_cientifica' => ['1e3'],
            'coma_decimal' => ['10,50'],
            'float' => [10.5],
            'booleano' => [true],
            'nulo' => [null],
            'vacio' => [''],
            'arreglo' => [[]],
        ];
    }

    #[DataProvider('importesInvalidos')]
    public function test_rechaza_importes_invalidos(
        mixed $importe
    ): void {
        $this->expectException(ValidationException::class);

        ImporteFinanciero::normalizar($importe);
    }
}
