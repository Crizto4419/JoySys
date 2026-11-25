<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Existencia extends Model
{
    protected $table = 'existencias';

    protected $fillable = [
        'producto_id',
        'sucursal_id',
        'estado_inventario_id',
        'cantidad',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:4',
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

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(
            Sucursal::class,
            'sucursal_id',
            'id'
        );
    }

    public function estadoInventario(): BelongsTo
    {
        return $this->belongsTo(
            EstadoInventario::class,
            'estado_inventario_id',
            'id'
        );
    }
}