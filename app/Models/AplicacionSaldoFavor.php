<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AplicacionSaldoFavor extends Model
{
    protected $table = 'aplicaciones_saldos_favor';

    protected $fillable = [
        'saldo_favor_id',
        'detalle_pago_id',
        'sucursal_id',
        'usuario_id',
        'revertida_por',
        'monto_aplicado',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'monto_aplicado' => 'decimal:4',
        ];
    }

    public function saldoFavor(): BelongsTo
    {
        return $this->belongsTo(
            SaldoFavor::class,
            'saldo_favor_id'
        );
    }

    public function detallePago(): BelongsTo
    {
        return $this->belongsTo(
            DetallePago::class,
            'detalle_pago_id'
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

    public function revertidaPor(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'revertida_por'
        );
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(
            MovimientoSaldoFavor::class,
            'aplicacion_saldo_favor_id'
        );
    }
}