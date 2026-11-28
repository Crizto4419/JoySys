<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaldoFavor extends Model
{
    protected $table = 'saldos_favor';

    protected $fillable = [
        'cliente_id',
        'sucursal_id',
        'usuario_id',
        'moneda',
        'saldo_disponible',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'saldo_disponible' => 'decimal:4',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function aplicaciones(): HasMany
    {
        return $this->hasMany(
            AplicacionSaldoFavor::class,
            'saldo_favor_id'
        );
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(
            MovimientoSaldoFavor::class,
            'saldo_favor_id'
        );
    }
}