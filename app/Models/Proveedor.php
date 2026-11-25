<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Proveedor extends Model
{
    use SoftDeletes;

    protected $table = 'proveedores';

    protected $fillable = [
        'nombre_comercial',
        'razon_social',
        'rfc',
        'descripcion',
        'direccion',
        'sitio_web',
        'estado',
        'observaciones',
    ];

    public function productos(): HasMany
    {
        return $this->hasMany(
            Producto::class,
            'proveedor_id',
            'id'
        );
    }
}