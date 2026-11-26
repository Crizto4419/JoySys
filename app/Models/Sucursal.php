<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Sucursal extends Model
{
    protected $table = 'sucursales';

    protected $fillable = [
        'nombre',
        'codigo',
        'direccion',
        'telefono',
        'estado',
    ];

    public function asignacionesEmpleados(): HasMany
    {
        return $this->hasMany(
            EmpleadoSucursal::class,
            'sucursal_id',
            'id'
        );
    }

    public function empleados(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'empleado_sucursal',
            'sucursal_id',
            'usuario_id'
        )->withPivot([
            'id',
            'es_principal',
            'fecha_inicio',
            'fecha_fin',
            'estado',
            'asignado_por',
        ])->withTimestamps();
    }

    public function empleadosVigentes(): BelongsToMany
    {
        return $this->empleados()
            ->wherePivot('estado', 'activo')
            ->wherePivotNull('fecha_fin')
            ->wherePivot(
                'fecha_inicio',
                '<=',
                now()->toDateString()
            );
    }

    public function piezas(): HasMany
    {
        return $this->hasMany(
            Pieza::class,
            'sucursal_id',
            'id'
        );
    }

    public function existencias(): HasMany
    {
        return $this->hasMany(
            Existencia::class,
            'sucursal_id',
            'id'
        );
    }

    public function movimientosInventario(): HasMany
    {
        return $this->hasMany(
            MovimientoInventario::class,
            'sucursal_id',
            'id'
        );
    }

    public function incidenciasInventario(): HasMany
    {
        return $this->hasMany(
            IncidenciaInventario::class,
            'sucursal_id',
            'id'
        );
    }

    public function ventas(): HasMany
    {
        return $this->hasMany(
            Venta::class,
            'sucursal_id',
            'id'
        );
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(
            Pago::class,
            'sucursal_id',
            'id'
        );
    }
}