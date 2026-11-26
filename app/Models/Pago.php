<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Pago extends Model
{
    protected $table = 'pagos';

    const UPDATED_AT = null;

    protected $fillable = [
        'sucursal_id',
        'referencia_tipo',
        'referencia_id',
        'pago_original_id',
        'recibido_por',
        'monto_total',
        'moneda',
        'estado',
        'fecha_pago',
    ];

    protected function casts(): array
    {
        return [
            'monto_total' => 'decimal:4',
            'fecha_pago' => 'datetime',
        ];
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(
            Sucursal::class,
            'sucursal_id',
            'id'
        );
    }

    public function recibidoPor(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'recibido_por',
            'id'
        );
    }

    public function referencia(): MorphTo
    {
        return $this->morphTo(
            'referencia',
            'referencia_tipo',
            'referencia_id',
            'id'
        );
    }

    public function pagoOriginal(): BelongsTo
    {
        return $this->belongsTo(
            self::class,
            'pago_original_id',
            'id'
        );
    }

    public function reembolsos(): HasMany
    {
        return $this->hasMany(
            self::class,
            'pago_original_id',
            'id'
        );
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(
            DetallePago::class,
            'pago_id',
            'id'
        );
    }
}