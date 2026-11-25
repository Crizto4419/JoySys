<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EstadoInventario extends Model
{
    protected $table = 'estados_inventario';

    protected $fillable = [
        'codigo',
        'nombre',
        'descripcion',
    ];

    public function piezas(): HasMany
    {
        return $this->hasMany(
            Pieza::class,
            'estado_inventario_id',
            'id'
        );
    }

    public function existencias(): HasMany
    {
        return $this->hasMany(
            Existencia::class,
            'estado_inventario_id',
            'id'
        );
    }
}