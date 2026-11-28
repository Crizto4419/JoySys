<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperacionIdempotente extends Model
{
    protected $table = 'operaciones_idempotentes';

    protected $fillable = [
        'sucursal_id',
        'usuario_id',
        'tipo_operacion',
        'clave',
        'hash_solicitud',
        'resultado_tipo',
        'resultado_id',
    ];

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}