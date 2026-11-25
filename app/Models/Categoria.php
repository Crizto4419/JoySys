<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Categoria extends Model
{
    use SoftDeletes;

    protected $table = 'categorias';

    protected $fillable = [
        'categoria_padre_id',
        'nombre',
        'descripcion',
        'estado',
    ];

    public function categoriaPadre(): BelongsTo
    {
        return $this->belongsTo(
            Categoria::class,
            'categoria_padre_id',
            'id'
        );
    }

    public function subcategorias(): HasMany
    {
        return $this->hasMany(
            Categoria::class,
            'categoria_padre_id',
            'id'
        );
    }

    public function productos(): HasMany
    {
        return $this->hasMany(
            Producto::class,
            'categoria_id',
            'id'
        );
    }
}