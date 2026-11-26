<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncidenciaInventario extends Model
{
    protected $table = 'incidencias_inventario';

    protected $fillable = [
        'producto_id',
        'sucursal_id',
        'usuario_reporta_id',
        'tipo_incidencia',
        'descripcion',
        'estado',
    ];

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

    public function usuarioReporta(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'usuario_reporta_id',
            'id'
        );
    }
}
