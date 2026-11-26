<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pieza extends Model
{
    protected $table = 'piezas';

    protected $fillable = [
        'producto_id',
        'sucursal_id',
        'estado_inventario_id',
        'metal_id',
        'pureza_id',
        'codigo_pieza',
        'peso_gramos',
    ];

    protected function casts(): array
    {
        return [
            'peso_gramos' => 'decimal:3',
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

    public function metal(): BelongsTo
    {
        return $this->belongsTo(
            Metal::class,
            'metal_id',
            'id'
        );
    }

    public function pureza(): BelongsTo
    {
        return $this->belongsTo(
            Pureza::class,
            'pureza_id',
            'id'
        );
    }

    public function movimientosInventario(): HasMany
    {
        return $this->hasMany(
            MovimientoInventario::class,
            'pieza_id',
            'id'
        );
    }

    public function detallesVenta(): HasMany
    {
        return $this->hasMany(
            DetalleVenta::class,
            'pieza_id',
            'id'
        );
    }
}
