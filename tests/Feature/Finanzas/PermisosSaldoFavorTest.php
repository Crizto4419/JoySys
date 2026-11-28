<?php

namespace Tests\Feature\Finanzas;

use Database\Seeders\PermisoSeeder;
use Database\Seeders\RolPermisoSeeder;
use Database\Seeders\PermisosSaldoFavorSeeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

final class PermisosSaldoFavorTest extends TestCase
{
    private const CLAVES = [
        'saldos_favor.generar',
        'saldos_favor.cancelar',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['administrador', 'encargado', 'empleado'] as $slug) {
            DB::table('roles')->insert([
                'nombre' => ucfirst($slug),
                'slug' => $slug,
                'descripcion' => null,
                'estado' => 'activo',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->seed(PermisoSeeder::class);
        $this->seed(RolPermisoSeeder::class);
    }

    private function permiso(string $clave): int
    {
        return (int) DB::table('permisos')
            ->where('clave', $clave)
            ->value('id');
    }

    private function rol(string $slug): int
    {
        return (int) DB::table('roles')
            ->where('slug', $slug)
            ->value('id');
    }

    private function asignado(string $rol, string $clave): bool
    {
        return DB::table('rol_permiso')
            ->where('rol_id', $this->rol($rol))
            ->where('permiso_id', $this->permiso($clave))
            ->exists();
    }

    private function retirarPermisosNuevos(): void
    {
        $ids = DB::table('permisos')
            ->whereIn('clave', self::CLAVES)
            ->pluck('id');

        DB::table('rol_permiso')
            ->whereIn('permiso_id', $ids)
            ->delete();

        DB::table('permisos')
            ->whereIn('id', $ids)
            ->delete();
    }

    private function verificarMatriz(): void
    {
        $this->assertTrue(
            $this->asignado('administrador', 'saldos_favor.generar')
        );

        $this->assertTrue(
            $this->asignado('administrador', 'saldos_favor.cancelar')
        );

        $this->assertTrue(
            $this->asignado('encargado', 'saldos_favor.generar')
        );

        $this->assertFalse(
            $this->asignado('encargado', 'saldos_favor.cancelar')
        );

        $this->assertFalse(
            $this->asignado('empleado', 'saldos_favor.generar')
        );

        $this->assertFalse(
            $this->asignado('empleado', 'saldos_favor.cancelar')
        );
    }

    private function instantanea(): array
    {
        return [
            'roles' => DB::table('roles')
                ->orderBy('id')->get()->toArray(),

            'permisos' => DB::table('permisos')
                ->orderBy('id')->get()->toArray(),

            'asignaciones' => DB::table('rol_permiso')
                ->orderBy('rol_id')
                ->orderBy('permiso_id')
                ->get()->toArray(),
        ];
    }

    public function test_psf01_instalacion_incremental(): void
    {
        $this->retirarPermisosNuevos();

        $permisos = DB::table('permisos')->count();
        $asignaciones = DB::table('rol_permiso')->count();

        $this->seed(PermisosSaldoFavorSeeder::class);

        $this->assertSame(
            $permisos + 2,
            DB::table('permisos')->count()
        );

        $this->assertSame(
            $asignaciones + 3,
            DB::table('rol_permiso')->count()
        );

        $this->verificarMatriz();
    }

    public function test_psf02_instalacion_idempotente(): void
    {
        $this->seed(PermisosSaldoFavorSeeder::class);

        $antes = $this->instantanea();

        $this->seed(PermisosSaldoFavorSeeder::class);

        $this->assertEquals($antes, $this->instantanea());
        $this->verificarMatriz();
    }

    public function test_psf03_conserva_permisos_existentes(): void
    {
        $this->retirarPermisosNuevos();

        $antes = $this->instantanea();

        $this->seed(PermisosSaldoFavorSeeder::class);

        $ids = DB::table('permisos')
            ->whereIn('clave', self::CLAVES)
            ->pluck('id');

        $this->assertEquals(
            $antes['roles'],
            DB::table('roles')->orderBy('id')->get()->toArray()
        );

        $this->assertEquals(
            $antes['permisos'],
            DB::table('permisos')
                ->whereNotIn('id', $ids)
                ->orderBy('id')
                ->get()->toArray()
        );

        $this->assertEquals(
            $antes['asignaciones'],
            DB::table('rol_permiso')
                ->whereNotIn('permiso_id', $ids)
                ->orderBy('rol_id')
                ->orderBy('permiso_id')
                ->get()->toArray()
        );
    }

    public function test_psf04_rechaza_asignacion_indebida(): void
    {
        DB::table('rol_permiso')->insert([
            'rol_id' => $this->rol('empleado'),
            'permiso_id' => $this->permiso('saldos_favor.cancelar'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('rol_permiso')
            ->where('rol_id', $this->rol('encargado'))
            ->where('permiso_id', $this->permiso('saldos_favor.generar'))
            ->delete();

        $antes = $this->instantanea();

        try {
            $this->seed(PermisosSaldoFavorSeeder::class);

            $this->fail('Se admitio una asignacion indebida.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString(
                'Asignacion incompatible',
                $e->getMessage()
            );
        }

        $this->assertEquals($antes, $this->instantanea());
    }

    public function test_psf05_repara_asignacion_faltante(): void
    {
        DB::table('rol_permiso')
            ->where('rol_id', $this->rol('administrador'))
            ->where('permiso_id', $this->permiso('saldos_favor.cancelar'))
            ->delete();

        $this->seed(PermisosSaldoFavorSeeder::class);

        $this->verificarMatriz();
    }

    public function test_psf06_rechaza_modulo_incompatible(): void
    {
        DB::table('permisos')
            ->where('clave', 'saldos_favor.cancelar')
            ->update(['modulo' => 'incompatible']);

        $antes = $this->instantanea();

        try {
            $this->seed(PermisosSaldoFavorSeeder::class);

            $this->fail('Se admitio un modulo incompatible.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString(
                'Modulo incompatible',
                $e->getMessage()
            );
        }

        $this->assertEquals($antes, $this->instantanea());
    }
}
