<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'username',
    'name',
    'apellidos',
    'codigo_empleado',
    'telefono',
    'email',
    'password',
])]
#[Hidden([
    'password',
    'remember_token',
])]
class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $table = 'users';

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'intentos_fallidos' => 'integer',
            'bloqueado_hasta' => 'datetime',
            'ultimo_acceso' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(
            Role::class,
            'role_id',
            'id'
        );
    }

    public function asignacionesSucursales(): HasMany
    {
        return $this->hasMany(
            EmpleadoSucursal::class,
            'usuario_id',
            'id'
        );
    }

    public function asignacionesRealizadas(): HasMany
    {
        return $this->hasMany(
            EmpleadoSucursal::class,
            'asignado_por',
            'id'
        );
    }

    public function sucursales(): BelongsToMany
    {
        return $this->belongsToMany(
            Sucursal::class,
            'empleado_sucursal',
            'usuario_id',
            'sucursal_id'
        )
            ->withPivot([
                'id',
                'es_principal',
                'fecha_inicio',
                'fecha_fin',
                'estado',
                'asignado_por',
            ])
            ->withTimestamps();
    }

    public function sucursalesVigentes(): BelongsToMany
    {
        return $this->sucursales()
            ->wherePivot('estado', 'activo')
            ->wherePivotNull('fecha_fin')
            ->wherePivot(
                'fecha_inicio',
                '<=',
                now()->toDateString()
            );
    }

    public function sucursalPrincipal(): BelongsToMany
    {
        return $this->sucursalesVigentes()
            ->wherePivot('es_principal', true);
    }

    public function movimientosInventario(): HasMany
    {
        return $this->hasMany(
            MovimientoInventario::class,
            'usuario_id',
            'id'
        );
    }

    public function incidenciasReportadas(): HasMany
    {
        return $this->hasMany(
            IncidenciaInventario::class,
            'usuario_reporta_id',
            'id'
        );
    }

    public function historialPrecios(): HasMany
    {
        return $this->hasMany(
            HistorialPrecio::class,
            'usuario_id',
            'id'
        );
    }
}
