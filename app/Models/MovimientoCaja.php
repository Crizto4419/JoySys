<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MovimientoCaja extends Model
{
    protected $table = 'movimientos_caja';

    const UPDATED_AT = null;

    protected $fillable = [
        'sesion_caja_id',
        'usuario_id',
        'autorizado_por',
        'detalle_pago_id',
        'movimiento_original_id',
        'tipo_movimiento',
        'monto',
        'concepto',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:4',
        ];
    }

    public function sesionCaja(): BelongsTo
    {
        return $this->belongsTo(
            SesionCaja::class,
            'sesion_caja_id',
            'id'
        );
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'usuario_id',
            'id'
        );
    }

    public function autorizadoPor(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'autorizado_por',
            'id'
        );
    }

    public function detallePago(): BelongsTo
    {
        return $this->belongsTo(
            DetallePago::class,
            'detalle_pago_id',
            'id'
        );
    }

    public function movimientoOriginal(): BelongsTo
    {
        return $this->belongsTo(
            self::class,
            'movimiento_original_id',
            'id'
        );
    }

    public function compensaciones(): HasMany
    {
        return $this->hasMany(
            self::class,
            'movimiento_original_id',
            'id'
        );
    }


    protected static function booted(): void
    {
        static::updating(function (self $movimiento): void {
            throw new \LogicException(
                'Los movimientos de caja son inmutables.'
            );
        });

        static::deleting(function (self $movimiento): void {
            throw new \LogicException(
                'Los movimientos de caja no pueden eliminarse.'
            );
        });
    }
}
