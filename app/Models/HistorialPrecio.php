<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistorialPrecio extends Model
{
    protected $table = 'historial_precios';

    const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(function () {
            throw new \LogicException(
                'El historial de precios no puede modificarse.'
            );
        });

        static::deleting(function () {
            throw new \LogicException(
                'El historial de precios no puede eliminarse.'
            );
        });
    }

    protected $fillable = [
        'producto_id',
        'precio_anterior',
        'precio_nuevo',
        'moneda',
        'vigente_desde',
        'usuario_id',
    ];

    protected function casts(): array
    {
        return [
            'precio_anterior' => 'decimal:4',
            'precio_nuevo' => 'decimal:4',
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

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'usuario_id',
            'id'
        )->withTrashed();
    }
}
