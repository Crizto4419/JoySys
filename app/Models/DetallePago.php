<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DetallePago extends Model
{
    protected $table = 'detalle_pagos';

    const UPDATED_AT = null;

    protected $fillable = [
        'pago_id',
        'metodo_pago',
        'monto',
        'referencia',
        'detalle_adicional',
        'detalle_pago_original_id',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:4',
        ];
    }

    public function pago(): BelongsTo
    {
        return $this->belongsTo(
            Pago::class,
            'pago_id',
            'id'
        );
    }

    public function detallePagoOriginal(): BelongsTo
    {
        return $this->belongsTo(
            self::class,
            'detalle_pago_original_id',
            'id'
        );
    }

    public function reembolsos(): HasMany
    {
        return $this->hasMany(
            self::class,
            'detalle_pago_original_id',
            'id'
        );
    }

    public function movimientoCaja(): HasOne
    {
        return $this->hasOne(
            MovimientoCaja::class,
            'detalle_pago_id',
            'id'
        );
    }
}