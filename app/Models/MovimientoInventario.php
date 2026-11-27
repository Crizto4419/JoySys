<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovimientoInventario extends Model
{
    protected $table = 'movimientos_inventario';

    const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(function () {
            throw new \LogicException(
                'Los movimientos de inventario no pueden modificarse.'
            );
        });

        static::deleting(function () {
            throw new \LogicException(
                'Los movimientos de inventario no pueden eliminarse.'
            );
        });
    }

    protected $fillable = [
        'producto_id',
        'sucursal_id',
        'pieza_id',
        'estado_origen_id',
        'estado_destino_id',
        'usuario_id',
        'tipo_movimiento',
        'cantidad',
        'observaciones',
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

    public function pieza(): BelongsTo
    {
        return $this->belongsTo(
            Pieza::class,
            'pieza_id',
            'id'
        );
    }

    public function estadoOrigen(): BelongsTo
    {
        return $this->belongsTo(
            EstadoInventario::class,
            'estado_origen_id',
            'id'
        );
    }

    public function estadoDestino(): BelongsTo
    {
        return $this->belongsTo(
            EstadoInventario::class,
            'estado_destino_id',
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
}
