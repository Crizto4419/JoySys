<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetalleVenta extends Model
{
    protected $table = 'detalle_ventas';

    protected $fillable = [
        'venta_id',
        'producto_id',
        'pieza_id',
        'cantidad',
        'precio_unitario',
        'descuento',
        'subtotal',
        'total',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:4',
            'precio_unitario' => 'decimal:4',
            'descuento' => 'decimal:4',
            'subtotal' => 'decimal:4',
            'total' => 'decimal:4',
        ];
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(
            Venta::class,
            'venta_id',
            'id'
        );
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(
            Producto::class,
            'producto_id',
            'id'
        );
    }

    public function pieza(): BelongsTo
    {
        return $this->belongsTo(
            Pieza::class,
            'pieza_id',
            'id'
        );
    }
}
