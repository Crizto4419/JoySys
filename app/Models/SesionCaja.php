<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SesionCaja extends Model
{
    protected $table = 'sesiones_caja';

    protected $fillable = [
        'caja_id',
        'sucursal_id',
        'usuario_id',
        'cerrada_por',
        'fondo_inicial',
        'saldo_esperado',
        'efectivo_contado',
        'diferencia',
        'fecha_apertura',
        'fecha_cierre',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'fondo_inicial' => 'decimal:4',
            'saldo_esperado' => 'decimal:4',
            'efectivo_contado' => 'decimal:4',
            'diferencia' => 'decimal:4',
            'fecha_apertura' => 'datetime',
            'fecha_cierre' => 'datetime',
        ];
    }

    public function caja(): BelongsTo
    {
        return $this->belongsTo(
            Caja::class,
            'caja_id',
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

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'usuario_id',
            'id'
        );
    }

    public function cerradaPor(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'cerrada_por',
            'id'
        );
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(
            MovimientoCaja::class,
            'sesion_caja_id',
            'id'
        );
    }
}