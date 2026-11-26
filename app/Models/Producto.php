<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Producto extends Model
{
    use SoftDeletes;

    protected $table = 'productos';

    protected $fillable = [
        'categoria_id',
        'proveedor_id',
        'metal_id',
        'pureza_id',
        'codigo',
        'nombre',
        'descripcion',
        'estado',
        'tipo',
        'precio_base',
        'stock_minimo',
    ];

    protected function casts(): array
    {
        return [
            'precio_base' => 'decimal:4',
            'stock_minimo' => 'decimal:4',
        ];
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(
            Categoria::class,
            'categoria_id',
            'id'
        );
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(
            Proveedor::class,
            'proveedor_id',
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

    public function piezas(): HasMany
    {
        return $this->hasMany(
            Pieza::class,
            'producto_id',
            'id'
        );
    }

    public function existencias(): HasMany
    {
        return $this->hasMany(
            Existencia::class,
            'producto_id',
            'id'
        );
    }

    public function movimientosInventario(): HasMany
    {
        return $this->hasMany(
            MovimientoInventario::class,
            'producto_id',
            'id'
        );
    }

    public function incidenciasInventario(): HasMany
    {
        return $this->hasMany(
            IncidenciaInventario::class,
            'producto_id',
            'id'
        );
    }

    public function precioVigente(): HasOne
    {
        return $this->hasOne(
            PrecioProducto::class,
            'producto_id',
            'id'
        );
    }

    public function historialPrecios(): HasMany
    {
        return $this->hasMany(
            HistorialPrecio::class,
            'producto_id',
            'id'
        );
    }
}
