<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrecioProducto extends Model
{
    protected $table = 'precios_productos';

    protected $fillable = [
        'producto_id',
        'precio',
        'moneda',
        'vigente_desde',
    ];

    protected function casts(): array
    {
        return [
            'precio' => 'decimal:4',
            'vigente_desde' => 'datetime',
        ];
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(
            Producto::class,
            'producto_id',
            'id'
        );
    }
}
