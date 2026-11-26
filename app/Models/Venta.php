<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Venta extends Model
{
    protected $table = 'ventas';

    protected $fillable = [
        'sucursal_id',
        'cliente_id',
        'usuario_id',
        'folio',
        'subtotal',
        'descuento_total',
        'impuestos',
        'total',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:4',
            'descuento_total' => 'decimal:4',
            'impuestos' => 'decimal:4',
            'total' => 'decimal:4',
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

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(
            Cliente::class,
            'cliente_id',
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

    public function detalles(): HasMany
    {
        return $this->hasMany(
            DetalleVenta::class,
            'venta_id',
            'id'
        );
    }
}
