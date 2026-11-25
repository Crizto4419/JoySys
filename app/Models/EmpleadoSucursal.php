<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmpleadoSucursal extends Model
{
    protected $table = 'empleado_sucursal';

    protected $fillable = [
        'usuario_id',
        'sucursal_id',
        'es_principal',
        'fecha_inicio',
        'fecha_fin',
        'estado',
        'asignado_por',
    ];

    protected function casts(): array
    {
        return [
            'es_principal' => 'boolean',
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'usuario_id',
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

    public function asignadoPor(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'asignado_por',
            'id'
        );
    }
}
