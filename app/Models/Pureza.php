<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pureza extends Model
{
    protected $table = 'purezas';

    protected $fillable = [
        'metal_id',
        'codigo',
        'nombre',
    ];

    public function metal(): BelongsTo
    {
        return $this->belongsTo(
            Metal::class,
            'metal_id',
            'id'
        );
    }

    public function productos(): HasMany
    {
        return $this->hasMany(
            Producto::class,
            'pureza_id',
            'id'
        );
    }

    public function piezas(): HasMany
    {
        return $this->hasMany(
            Pieza::class,
            'pureza_id',
            'id'
        );
    }
}