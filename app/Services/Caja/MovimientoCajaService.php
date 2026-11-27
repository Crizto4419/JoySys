<?php

namespace App\Services\Caja;

use App\Models\Caja;
use App\Models\DetallePago;
use App\Models\Pago;
use App\Models\MovimientoCaja;
use App\Models\SesionCaja;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MovimientoCajaService
{
    public const ESCALA = 4;

    public const MAXIMO_MONETARIO = '99999999.9999';

    public const TIPOS = [
        'ingreso',
        'gasto',
        'retiro',
        'reembolso',
        'compensacion',
    ];

    public static function normalizarMonto(
        mixed $importe,
        bool $permitirCero = false,
        string $campo = 'monto'
    ): string {
        if (! is_string($importe) && ! is_int($importe)) {
            throw ValidationException::withMessages([
                $campo => 'El importe debe ser un decimal válido.',
            ]);
        }

        $importe = trim((string) $importe);

        if (! preg_match(
            '/\A[0-9]{1,8}(?:\.[0-9]{1,4})?\z/',
            $importe
        )) {
            throw ValidationException::withMessages([
                $campo => 'El importe debe tener hasta ocho enteros y cuatro decimales.',
            ]);
        }

        $normalizado = bcadd(
            $importe,
            '0',
            self::ESCALA
        );

        if (
            ! $permitirCero
            && bccomp(
                $normalizado,
                '0',
                self::ESCALA
            ) <= 0
        ) {
            throw ValidationException::withMessages([
                $campo => 'El importe debe ser mayor que cero.',
            ]);
        }

        return $normalizado;
    }

    public static function verificarRango(
        string $importe,
        string $campo = 'monto'
    ): string {
        if (
            ! preg_match(
                '/\A-?[0-9]+(?:\.[0-9]{1,4})?\z/',
                $importe
            )
        ) {
            throw ValidationException::withMessages([
                $campo => 'El resultado monetario no es válido.',
            ]);
        }

        if (
            bccomp(
                $importe,
                self::MAXIMO_MONETARIO,
                self::ESCALA
            ) > 0
            || bccomp(
                $importe,
                '-'.self::MAXIMO_MONETARIO,
                self::ESCALA
            ) < 0
        ) {
            throw ValidationException::withMessages([
                $campo => 'El resultado excede el rango monetario permitido.',
            ]);
        }

        return bcadd(
            $importe,
            '0',
            self::ESCALA
        );
    }

    public static function direccionBase(
        string $tipo
    ): int {
        return match ($tipo) {
            'ingreso' => 1,
            'gasto', 'retiro', 'reembolso' => -1,

            default => throw ValidationException::withMessages([
                'tipo_movimiento' => 'El tipo de movimiento no tiene una dirección base válida.',
            ]),
        };
    }

    public static function efecto(
        string $tipo,
        mixed $monto,
        ?string $tipoOriginal = null
    ): string {
        $monto = self::normalizarMonto($monto);

        if ($tipo === 'compensacion') {
            if ($tipoOriginal === null) {
                throw ValidationException::withMessages([
                    'movimiento_original_id' => 'La compensación requiere un movimiento original.',
                ]);
            }

            $direccion = -self::direccionBase(
                $tipoOriginal
            );
        } else {
            if ($tipoOriginal !== null) {
                throw ValidationException::withMessages([
                    'movimiento_original_id' => 'Solo las compensaciones admiten un movimiento original.',
                ]);
            }

            $direccion = self::direccionBase($tipo);
        }

        return bcmul(
            $monto,
            (string) $direccion,
            self::ESCALA
        );
    }

    public static function efectoMovimiento(
        MovimientoCaja $movimiento
    ): string {
        $tipoOriginal = null;

        if (
            $movimiento->tipo_movimiento
            === 'compensacion'
        ) {
            $original = $movimiento->movimientoOriginal;

            if ($original === null) {
                throw ValidationException::withMessages([
                    'movimiento_original_id' => 'No existe el movimiento original.',
                ]);
            }

            $tipoOriginal = $original->tipo_movimiento;
        } elseif (
            $movimiento->movimiento_original_id !== null
        ) {
            throw ValidationException::withMessages([
                'movimiento_original_id' => 'El movimiento contiene una relación original inválida.',
            ]);
        }

        return self::efecto(
            $movimiento->tipo_movimiento,
            $movimiento->monto,
            $tipoOriginal
        );
    }

    public static function calcularSaldo(
        mixed $fondoInicial,
        iterable $movimientos
    ): string {
        $saldo = self::normalizarMonto(
            $fondoInicial,
            true,
            'fondo_inicial'
        );

        foreach ($movimientos as $movimiento) {
            if (! $movimiento instanceof MovimientoCaja) {
                throw ValidationException::withMessages([
                    'movimientos' => 'Se recibió un movimiento inválido.',
                ]);
            }

            $saldo = bcadd(
                $saldo,
                self::efectoMovimiento($movimiento),
                self::ESCALA
            );
        }

        return self::verificarRango(
            $saldo,
            'saldo_esperado'
        );
    }

    public static function calcularDiferencia(
        mixed $efectivoContado,
        string $saldoEsperado
    ): string {
        $contado = self::normalizarMonto(
            $efectivoContado,
            true,
            'efectivo_contado'
        );

        $esperado = self::verificarRango(
            $saldoEsperado,
            'saldo_esperado'
        );

        return self::verificarRango(
            bcsub(
                $contado,
                $esperado,
                self::ESCALA
            ),
            'diferencia'
        );
    }


    public function __construct(
        private readonly CajaService $cajas
    ) {}

    public static function reglasSalida(): array
    {
        return [
            'monto' => ['required'],
            'concepto' => ['required', 'string', 'max:150'],
            'observaciones' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public static function validarDatosSalida(array $datos): array
    {
        $permitidos = [
            'monto',
            'concepto',
            'observaciones',
        ];

        foreach (array_keys($datos) as $campo) {
            if (! in_array($campo, $permitidos, true)) {
                throw ValidationException::withMessages([
                    $campo => 'Este campo no puede proporcionarse.',
                ]);
            }
        }

        foreach (['concepto', 'observaciones'] as $campo) {
            if (
                isset($datos[$campo])
                && is_string($datos[$campo])
            ) {
                $datos[$campo] = trim($datos[$campo]);
            }
        }

        $datos['monto'] = self::normalizarMonto(
            $datos['monto'] ?? null
        );

        $validados = Validator::make(
            $datos,
            self::reglasSalida()
        )->validate();

        return [
            'monto' => $validados['monto'],
            'concepto' => $validados['concepto'],
            'observaciones' => (
                $validados['observaciones'] ?? ''
            ) !== ''
                ? $validados['observaciones']
                : null,
        ];
    }

    public function listarMovimientos(
        SesionCaja $sesion,
        User $usuario,
        array $filtros = []
    ): LengthAwarePaginator {
        $sucursalId = $this->cajas->autorizar(
            $usuario,
            'caja.ver'
        );

        $actual = SesionCaja::query()
            ->where('sucursal_id', $sucursalId)
            ->where('usuario_id', $usuario->getKey())
            ->findOrFail($sesion->getKey());

        $filtros = Validator::make($filtros, [
            'tipo_movimiento' => [
                'nullable',
                Rule::in(self::TIPOS),
            ],
            'page' => ['nullable', 'integer', 'min:1'],
        ])->validate();

        $consulta = $actual->movimientos()
            ->with([
                'usuario',
                'autorizadoPor',
                'movimientoOriginal',
            ]);

        if (! empty($filtros['tipo_movimiento'])) {
            $consulta->where(
                'tipo_movimiento',
                $filtros['tipo_movimiento']
            );
        }

        return $consulta
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();
    }

    public function registrarGasto(
        array $datos,
        User $usuario,
        ?int $sesionId = null
    ): MovimientoCaja {
        return $this->registrarSalida(
            'gasto',
            $datos,
            $usuario,
            $sesionId
        );
    }

    public function registrarRetiro(
        array $datos,
        User $usuario,
        ?int $sesionId = null
    ): MovimientoCaja {
        return $this->registrarSalida(
            'retiro',
            $datos,
            $usuario,
            $sesionId
        );
    }

    private function obtenerSesionAbierta(
        User $usuario,
        int $sucursalId
    ): SesionCaja {
        $sesion = SesionCaja::query()
            ->where('usuario_id', $usuario->getKey())
            ->where('sucursal_id', $sucursalId)
            ->where('estado', 'abierta')
            ->lockForUpdate()
            ->first();

        if ($sesion === null) {
            throw ValidationException::withMessages([
                'sesion_caja' => 'No tienes una sesion de caja abierta.',
            ]);
        }

        $caja = Caja::query()
            ->where('sucursal_id', $sucursalId)
            ->lockForUpdate()
            ->findOrFail($sesion->caja_id);

        if (
            $caja->estado !== 'activa'
            || $caja->moneda !== 'MXN'
        ) {
            throw ValidationException::withMessages([
                'caja' => 'La caja no esta disponible para operar.',
            ]);
        }

        return $sesion;
    }

    private function registrarSalida(
        string $tipo,
        array $datos,
        User $usuario,
        ?int $sesionId = null
    ): MovimientoCaja {
        $permiso = match ($tipo) {
            'gasto' => 'caja.registrar_gasto',
            'retiro' => 'caja.registrar_retiro',

            default => throw ValidationException::withMessages([
                'tipo_movimiento' => 'Tipo de salida no permitido.',
            ]),
        };

        $sucursalId = $this->cajas->autorizar(
            $usuario,
            $permiso
        );

        $datos = self::validarDatosSalida($datos);

        return $this->cajas->ejecutar(
            $usuario,
            $sucursalId,
            function () use (
                $usuario,
                $sucursalId,
                $datos,
                $tipo,
                $sesionId
            ) {
                $sesion = $this->obtenerSesionAbierta(
                    $usuario,
                    $sucursalId
                );

                if (
                    $sesionId !== null
                    && (int) $sesion->getKey() !== $sesionId
                ) {
                    throw ValidationException::withMessages([
                        'sesion_caja' =>
                            'La solicitud pertenece a otra sesion.',
                    ]);
                }

                $movimientos = $sesion->movimientos()
                    ->with('movimientoOriginal')
                    ->orderBy('id')
                    ->get();

                $saldo = self::calcularSaldo(
                    $sesion->fondo_inicial,
                    $movimientos
                );

                if (
                    bccomp(
                        $saldo,
                        $datos['monto'],
                        self::ESCALA
                    ) < 0
                ) {
                    throw ValidationException::withMessages([
                        'monto' => 'El efectivo disponible es insuficiente.',
                    ]);
                }

                self::verificarRango(
                    bcsub(
                        $saldo,
                        $datos['monto'],
                        self::ESCALA
                    ),
                    'saldo_esperado'
                );

                $movimiento = MovimientoCaja::query()->create([
                    'sesion_caja_id' => $sesion->getKey(),
                    'usuario_id' => $usuario->getKey(),
                    'autorizado_por' => $usuario->getKey(),
                    'detalle_pago_id' => null,
                    'movimiento_original_id' => null,
                    'tipo_movimiento' => $tipo,
                    'monto' => $datos['monto'],
                    'concepto' => $datos['concepto'],
                    'observaciones' => $datos['observaciones'],
                ]);

                return $movimiento->refresh()->load([
                    'usuario',
                    'autorizadoPor',
                ]);
            },
            false,
            $permiso
        );
    }


    public static function validarDatosCompensacion(
        array $datos
    ): array {
        $datos = self::validarDatosSalida($datos);

        if ($datos['observaciones'] === null) {
            throw ValidationException::withMessages([
                'observaciones' => 'La justificacion de la compensacion es obligatoria.',
            ]);
        }

        return $datos;
    }

    public static function calcularPendienteCompensacion(
        mixed $montoOriginal,
        iterable $importesCompensados
    ): string {
        $original = self::normalizarMonto($montoOriginal);

        $acumulado = '0.0000';

        foreach ($importesCompensados as $importe) {
            $acumulado = bcadd(
                $acumulado,
                self::normalizarMonto($importe),
                self::ESCALA
            );
        }

        if (
            bccomp(
                $acumulado,
                $original,
                self::ESCALA
            ) > 0
        ) {
            throw ValidationException::withMessages([
                'monto' => 'El historial contiene compensaciones superiores al importe original.',
            ]);
        }

        return bcsub(
            $original,
            $acumulado,
            self::ESCALA
        );
    }

    public function registrarCompensacion(
        MovimientoCaja $movimientoOriginal,
        array $datos,
        User $usuario
    ): MovimientoCaja {
        $permiso = 'caja.autorizar_compensacion';

        $sucursalId = $this->cajas->autorizar(
            $usuario,
            $permiso
        );

        $datos = self::validarDatosCompensacion($datos);

        return $this->cajas->ejecutar(
            $usuario,
            $sucursalId,
            function () use (
                $movimientoOriginal,
                $datos,
                $usuario,
                $sucursalId
            ) {
                $sesion = $this->obtenerSesionAbierta(
                    $usuario,
                    $sucursalId
                );

                $original = MovimientoCaja::query()
                    ->where('sesion_caja_id', $sesion->getKey())
                    ->lockForUpdate()
                    ->findOrFail($movimientoOriginal->getKey());

                if (
                    ! in_array(
                        $original->tipo_movimiento,
                        ['gasto', 'retiro'],
                        true
                    )
                    || $original->detalle_pago_id !== null
                    || $original->movimiento_original_id !== null
                ) {
                    throw ValidationException::withMessages([
                        'movimiento_original_id' => 'El movimiento no admite compensacion manual.',
                    ]);
                }

                $compensaciones = $original->compensaciones()
                    ->lockForUpdate()
                    ->get();

                foreach ($compensaciones as $compensacion) {
                    if (
                        $compensacion->tipo_movimiento !== 'compensacion'
                        || (string) $compensacion->sesion_caja_id
                            !== (string) $sesion->getKey()
                        || $compensacion->detalle_pago_id !== null
                        || $compensacion->autorizado_por === null
                    ) {
                        throw ValidationException::withMessages([
                            'movimiento_original_id' => 'El historial de compensaciones es inconsistente.',
                        ]);
                    }
                }

                $pendiente = self::calcularPendienteCompensacion(
                    $original->monto,
                    $compensaciones->pluck('monto')
                );

                if (
                    bccomp(
                        $datos['monto'],
                        $pendiente,
                        self::ESCALA
                    ) > 0
                ) {
                    throw ValidationException::withMessages([
                        'monto' => 'El importe supera el saldo pendiente de compensacion.',
                    ]);
                }

                $movimientos = $sesion->movimientos()
                    ->with('movimientoOriginal')
                    ->orderBy('id')
                    ->get();

                $saldo = self::calcularSaldo(
                    $sesion->fondo_inicial,
                    $movimientos
                );

                $efecto = self::efecto(
                    'compensacion',
                    $datos['monto'],
                    $original->tipo_movimiento
                );

                self::verificarRango(
                    bcadd(
                        $saldo,
                        $efecto,
                        self::ESCALA
                    ),
                    'saldo_esperado'
                );

                $compensacion = MovimientoCaja::query()->create([
                    'sesion_caja_id' => $sesion->getKey(),
                    'usuario_id' => $usuario->getKey(),
                    'autorizado_por' => $usuario->getKey(),
                    'detalle_pago_id' => null,
                    'movimiento_original_id' => $original->getKey(),
                    'tipo_movimiento' => 'compensacion',
                    'monto' => $datos['monto'],
                    'concepto' => $datos['concepto'],
                    'observaciones' => $datos['observaciones'],
                ]);

                return $compensacion->refresh()->load([
                    'usuario',
                    'autorizadoPor',
                    'movimientoOriginal',
                ]);
            },
            false,
            $permiso
        );
    }


    public const METODOS_PAGO = [
        'efectivo',
        'tarjeta_credito',
        'tarjeta_debito',
        'transferencia',
        'saldo_favor',
        'otro',
    ];

    public static function determinarTipoMovimientoPago(
        string $metodo,
        bool $esReembolso
    ): ?string {
        if (! in_array($metodo, self::METODOS_PAGO, true)) {
            throw ValidationException::withMessages([
                'metodo_pago' => 'Metodo de pago no reconocido.',
            ]);
        }

        if ($metodo !== 'efectivo') {
            return null;
        }

        return $esReembolso
            ? 'reembolso'
            : 'ingreso';
    }

    public function registrarMovimientoPago(
        DetallePago $detallePago,
        User $usuario
    ): ?MovimientoCaja {
        $permiso = 'pagos.registrar';

        $sucursalId = $this->cajas->autorizar(
            $usuario,
            $permiso
        );

        return $this->cajas->ejecutar(
            $usuario,
            $sucursalId,
            function () use (
                $detallePago,
                $usuario,
                $sucursalId
            ) {
                $detalle = DetallePago::query()
                    ->lockForUpdate()
                    ->findOrFail($detallePago->getKey());

                $pago = Pago::query()
                    ->where('sucursal_id', $sucursalId)
                    ->lockForUpdate()
                    ->findOrFail($detalle->pago_id);

                if (
                    $pago->estado !== 'confirmado'
                    || $pago->fecha_pago === null
                ) {
                    throw ValidationException::withMessages([
                        'pago' => 'El pago debe estar confirmado.',
                    ]);
                }

                if ($pago->moneda !== 'MXN') {
                    throw ValidationException::withMessages([
                        'moneda' => 'La moneda del pago no es compatible.',
                    ]);
                }

                if (
                    (string) $pago->recibido_por
                    !== (string) $usuario->getKey()
                ) {
                    throw ValidationException::withMessages([
                        'pago' => 'El pago pertenece a otro usuario.',
                    ]);
                }

                $esReembolso = $pago->pago_original_id !== null;

                if ($esReembolso) {
                    throw ValidationException::withMessages([
                        'pago' => 'El reembolso requiere el servicio financiero de autorizacion.',
                    ]);
                }

                if ($detalle->detalle_pago_original_id !== null) {
                    throw ValidationException::withMessages([
                        'detalle_pago' => 'El detalle contiene una referencia original incompatible.',
                    ]);
                }

                $monto = self::normalizarMonto(
                    $detalle->monto
                );

                $totalPago = self::normalizarMonto(
                    $pago->monto_total
                );

                $detalles = $pago->detalles()
                    ->lockForUpdate()
                    ->get();

                $totalDetalles = '0.0000';

                foreach ($detalles as $partida) {
                    if (
                        ! in_array(
                            $partida->metodo_pago,
                            self::METODOS_PAGO,
                            true
                        )
                        || $partida->detalle_pago_original_id !== null
                    ) {
                        throw ValidationException::withMessages([
                            'detalle_pago' => 'El pago contiene detalles incompatibles.',
                        ]);
                    }

                    $totalDetalles = bcadd(
                        $totalDetalles,
                        self::normalizarMonto($partida->monto),
                        self::ESCALA
                    );
                }

                if (
                    bccomp(
                        $totalDetalles,
                        $totalPago,
                        self::ESCALA
                    ) !== 0
                ) {
                    throw ValidationException::withMessages([
                        'pago' => 'La suma de detalles no coincide con el total del pago.',
                    ]);
                }

                $tipo = self::determinarTipoMovimientoPago(
                    $detalle->metodo_pago,
                    false
                );

                $existente = MovimientoCaja::query()
                    ->where('detalle_pago_id', $detalle->getKey())
                    ->lockForUpdate()
                    ->first();

                if ($existente !== null) {
                    $sesionOriginal = SesionCaja::query()
                        ->where('sucursal_id', $sucursalId)
                        ->where('usuario_id', $usuario->getKey())
                        ->findOrFail($existente->sesion_caja_id);

                    if (
                        $tipo === null
                        || $existente->tipo_movimiento !== $tipo
                        || $existente->movimiento_original_id !== null
                        || (string) $existente->usuario_id
                            !== (string) $usuario->getKey()
                        || bccomp(
                            $existente->monto,
                            $monto,
                            self::ESCALA
                        ) !== 0
                    ) {
                        throw ValidationException::withMessages([
                            'detalle_pago' => 'Existe un movimiento financiero inconsistente.',
                        ]);
                    }

                    return $existente;
                }

                if ($tipo === null) {
                    return null;
                }

                $sesion = $this->obtenerSesionAbierta(
                    $usuario,
                    $sucursalId
                );

                if (
                    $pago->fecha_pago->lt($sesion->fecha_apertura)
                    || $pago->fecha_pago->isFuture()
                ) {
                    throw ValidationException::withMessages([
                        'pago' => 'La fecha del pago no corresponde a la sesion abierta.',
                    ]);
                }

                $movimientos = $sesion->movimientos()
                    ->with('movimientoOriginal')
                    ->orderBy('id')
                    ->get();

                $saldo = self::calcularSaldo(
                    $sesion->fondo_inicial,
                    $movimientos
                );

                self::verificarRango(
                    bcadd(
                        $saldo,
                        $monto,
                        self::ESCALA
                    ),
                    'saldo_esperado'
                );

                $movimiento = MovimientoCaja::query()->create([
                    'sesion_caja_id' => $sesion->getKey(),
                    'usuario_id' => $usuario->getKey(),
                    'autorizado_por' => null,
                    'detalle_pago_id' => $detalle->getKey(),
                    'movimiento_original_id' => null,
                    'tipo_movimiento' => 'ingreso',
                    'monto' => $monto,
                    'concepto' => 'Ingreso por pago #'.$pago->getKey(),
                    'observaciones' => null,
                ]);

                return $movimiento->refresh()->load([
                    'usuario',
                    'detallePago',
                ]);
            },
            false,
            $permiso
        );
    }
}
