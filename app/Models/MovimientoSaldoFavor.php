<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class MovimientoSaldoFavor extends Model
{
    protected $table = 'movimientos_saldos_favor';

    public const UPDATED_AT = null;

    protected $fillable = [
        'saldo_favor_id',
        'sucursal_id',
        'usuario_id',
        'autorizado_por',
        'tipo_movimiento',
        'monto',
        'saldo_anterior',
        'saldo_nuevo',
        'aplicacion_saldo_favor_id',
        'pago_id',
        'devolucion_id',
        'movimiento_original_id',
        'justificacion',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:4',
            'saldo_anterior' => 'decimal:4',
            'saldo_nuevo' => 'decimal:4',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $movimiento): void {
            throw new LogicException(
                'Los movimientos de saldo a favor son inmutables.'
            );
        });

        static::deleting(function (self $movimiento): void {
            throw new LogicException(
                'Los movimientos de saldo a favor no pueden eliminarse.'
            );
        });
    }

    public function saldoFavor(): BelongsTo
    {
        return $this->belongsTo(
            SaldoFavor::class,
            'saldo_favor_id'
        );
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function autorizadoPor(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'autorizado_por'
        );
    }

    public function aplicacion(): BelongsTo
    {
        return $this->belongsTo(
            AplicacionSaldoFavor::class,
            'aplicacion_saldo_favor_id'
        );
    }

    public function pago(): BelongsTo
    {
        return $this->belongsTo(Pago::class);
    }

    public function movimientoOriginal(): BelongsTo
    {
        return $this->belongsTo(
            self::class,
            'movimiento_original_id'
        );
    }

    public function restituciones(): HasMany
    {
        return $this->hasMany(
            self::class,
            'movimiento_original_id'
        )->where('tipo_movimiento', 'restitucion');
    }
}