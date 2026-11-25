<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Metal extends Model
{
    protected $table = 'metales';

    protected $fillable = [
        'nombre',
        'simbolo',
        'descripcion',
        'estado',
    ];

    public function purezas(): HasMany
    {
        return $this->hasMany(
            Pureza::class,
            'metal_id',
            'id'
        );
    }

    public function productos(): HasMany
    {
        return $this->hasMany(
            Producto::class,
            'metal_id',
            'id'
        );
    }

    public function piezas(): HasMany
    {
        return $this->hasMany(
            Pieza::class,
            'metal_id',
            'id'
        );
    }
}