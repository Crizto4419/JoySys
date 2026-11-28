<?php

namespace Tests\Unit\Finanzas;

use App\Services\Finanzas\ValidadorReembolso;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ValidadorReembolsoTest extends TestCase
{
    private function datos(): array
    {
        return [
            'justificacion' => ' Devolucion autorizada ',
            'detalles' => [
                [
                    'detalle_pago_original_id' => 1,
                    'monto' => '250',
                ],
            ],
        ];
    }

    public function test_r01_disponible_original(): void
    {
        $this->assertSame(
            '1000.0000',
            ValidadorReembolso::disponible('1000', [])
        );
    }

    public function test_r02_reembolsos_acumulados(): void
    {
        $this->assertSame(
            '500.0000',
            ValidadorReembolso::disponible(
                '1000',
                ['200', '300']
            )
        );
    }

    public function test_r03_reembolso_total(): void
    {
        $this->assertSame(
            '0.0000',
            ValidadorReembolso::disponible(
                '1000',
                ['1000']
            )
        );
    }

    public function test_r04_precision_decimal(): void
    {
        $this->assertSame(
            '0.0001',
            ValidadorReembolso::disponible(
                '100.0000',
                ['99.9999']
            )
        );
    }

    public function test_r05_rechaza_exceso_historico(): void
    {
        $this->expectException(ValidationException::class);

        ValidadorReembolso::disponible(
            '100',
            ['100.0001']
        );
    }

    public function test_r06_limite_parcial(): void
    {
        $this->assertSame(
            '250.0000',
            ValidadorReembolso::verificarLimite(
                '250',
                '600',
                ['100']
            )
        );
    }

    public function test_r07_limite_total(): void
    {
        $this->assertSame(
            '500.0000',
            ValidadorReembolso::verificarLimite(
                '500',
                '600',
                ['100']
            )
        );
    }

    public function test_r08_rechaza_exceso(): void
    {
        $this->expectException(ValidationException::class);

        ValidadorReembolso::verificarLimite(
            '500.0001',
            '600',
            ['100']
        );
    }

    public function test_r09_rechaza_partida_agotada(): void
    {
        $this->expectException(ValidationException::class);

        ValidadorReembolso::verificarLimite(
            '0.0001',
            '100',
            ['100']
        );
    }

    public function test_r10_normaliza_solicitud(): void
    {
        $resultado = ValidadorReembolso::validar(
            $this->datos()
        );

        $this->assertSame(
            'Devolucion autorizada',
            $resultado['justificacion']
        );

        $this->assertSame(
            '250.0000',
            $resultado['monto_total']
        );

        $this->assertSame(
            1,
            $resultado['detalles'][0]['detalle_pago_original_id']
        );
    }

    public function test_r11_multiples_partidas(): void
    {
        $datos = $this->datos();

        $datos['detalles'][] = [
            'detalle_pago_original_id' => 2,
            'monto' => '150.1234',
        ];

        $resultado = ValidadorReembolso::validar($datos);

        $this->assertSame(
            '400.1234',
            $resultado['monto_total']
        );

        $this->assertCount(2, $resultado['detalles']);
    }

    public function test_r12_rechaza_partida_duplicada(): void
    {
        $datos = $this->datos();

        $datos['detalles'][] = [
            'detalle_pago_original_id' => '1',
            'monto' => '100',
        ];

        $this->expectException(ValidationException::class);

        ValidadorReembolso::validar($datos);
    }

    public static function justificacionesInvalidas(): array
    {
        return [
            'nula' => [null],
            'vacia' => [''],
            'espacios' => ['   '],
            'numerica' => [123],
            'larga' => [str_repeat('a', 256)],
            'arreglo' => [[]],
        ];
    }

    #[DataProvider('justificacionesInvalidas')]
    public function test_r13_justificacion_invalida(
        mixed $valor
    ): void {
        $this->expectException(ValidationException::class);

        ValidadorReembolso::justificar($valor);
    }

    public static function identificadoresInvalidos(): array
    {
        return [
            'cero' => [0],
            'negativo' => [-1],
            'decimal' => ['1.5'],
            'booleano' => [true],
            'nulo' => [null],
            'arreglo' => [[]],
        ];
    }

    #[DataProvider('identificadoresInvalidos')]
    public function test_r14_identificador_invalido(
        mixed $valor
    ): void {
        $datos = $this->datos();

        $datos['detalles'][0]['detalle_pago_original_id'] = $valor;

        $this->expectException(ValidationException::class);

        ValidadorReembolso::validar($datos);
    }

    public function test_r15_protege_metodo_original(): void
    {
        $datos = $this->datos();

        $datos['detalles'][0]['metodo_pago'] = 'efectivo';

        $this->expectException(ValidationException::class);

        ValidadorReembolso::validar($datos);
    }

    public function test_r16_protege_total(): void
    {
        $datos = $this->datos();

        $datos['monto_total'] = '1';

        $this->expectException(ValidationException::class);

        ValidadorReembolso::validar($datos);
    }

    public function test_r17_protege_usuario(): void
    {
        $datos = $this->datos();

        $datos['recibido_por'] = 999;

        $this->expectException(ValidationException::class);

        ValidadorReembolso::validar($datos);
    }

    public function test_r18_rechaza_detalles_vacios(): void
    {
        $datos = $this->datos();

        $datos['detalles'] = [];

        $this->expectException(ValidationException::class);

        ValidadorReembolso::validar($datos);
    }

    public function test_r19_rechaza_importe_cero(): void
    {
        $datos = $this->datos();

        $datos['detalles'][0]['monto'] = '0';

        $this->expectException(ValidationException::class);

        ValidadorReembolso::validar($datos);
    }

    public function test_r20_rechaza_desbordamiento(): void
    {
        $datos = $this->datos();

        $datos['detalles'] = [
            [
                'detalle_pago_original_id' => 1,
                'monto' => '99999999.9999',
            ],
            [
                'detalle_pago_original_id' => 2,
                'monto' => '0.0001',
            ],
        ];

        $this->expectException(ValidationException::class);

        ValidadorReembolso::validar($datos);
    }
}
