<?php

namespace Tests\Feature\Catalogos;

use App\Models\Categoria;
use App\Models\Metal;
use App\Models\Pureza;
use App\Services\Catalogos\CategoriaService;
use App\Services\Catalogos\MetalService;
use App\Services\Catalogos\PurezaService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CatalogosTest extends TestCase
{
    private function categorias(): CategoriaService
    {
        return app(CategoriaService::class);
    }

    private function metales(): MetalService
    {
        return app(MetalService::class);
    }

    private function purezas(): PurezaService
    {
        return app(PurezaService::class);
    }

    private function crearCategoria(
        string $nombre = 'Anillos',
        ?int $padreId = null
    ): Categoria {
        return $this->categorias()->crear([
            'nombre' => $nombre,
            'categoria_padre_id' => $padreId,
            'descripcion' => null,
        ]);
    }

    private function crearMetal(
        string $nombre = 'Metal de prueba',
        ?string $simbolo = 'MP'
    ): Metal {
        return $this->metales()->crear([
            'nombre' => $nombre,
            'simbolo' => $simbolo,
            'descripcion' => null,
        ]);
    }

    private function crearPureza(Metal $metal): Pureza
    {
        return $this->purezas()->crear([
            'metal_id' => $metal->id,
            'codigo' => '10k',
            'nombre' => 'Pureza de prueba',
        ]);
    }

    public function test_categoria_principal_se_registra_activa(): void
    {
        $categoria = $this->crearCategoria();

        $this->assertSame('Anillos', $categoria->nombre);
        $this->assertSame('activa', $categoria->estado);
        $this->assertNull($categoria->categoria_padre_id);

        $this->assertDatabaseHas('categorias', [
            'id' => $categoria->id,
            'nombre' => 'Anillos',
            'estado' => 'activa',
        ]);
    }

    public function test_categoria_permite_subcategorias(): void
    {
        $padre = $this->crearCategoria();
        $hija = $this->crearCategoria('Anillos infantiles', $padre->id);

        $this->assertEquals($padre->id, $hija->categoria_padre_id);
        $this->assertTrue($hija->categoriaPadre->is($padre));
    }

    public function test_categoria_rechaza_nombres_duplicados(): void
    {
        $this->crearCategoria('Anillos');

        $this->expectException(ValidationException::class);

        $this->crearCategoria('anillos');
    }

    public function test_categoria_permite_nombre_igual_en_distintos_padres(): void
    {
        $padreA = $this->crearCategoria('Oro');
        $padreB = $this->crearCategoria('Plata');

        $a = $this->crearCategoria('Anillos', $padreA->id);
        $b = $this->crearCategoria('Anillos', $padreB->id);

        $this->assertNotEquals($a->id, $b->id);
    }

    public function test_categoria_rechaza_autorreferencia(): void
    {
        $categoria = $this->crearCategoria();

        $this->expectException(ValidationException::class);

        $this->categorias()->actualizar($categoria, [
            'nombre' => $categoria->nombre,
            'categoria_padre_id' => $categoria->id,
            'descripcion' => null,
        ]);
    }

    public function test_categoria_rechaza_ciclos(): void
    {
        $padre = $this->crearCategoria();
        $hija = $this->crearCategoria('Infantiles', $padre->id);

        $this->expectException(ValidationException::class);

        $this->categorias()->actualizar($padre, [
            'nombre' => $padre->nombre,
            'categoria_padre_id' => $hija->id,
            'descripcion' => null,
        ]);
    }

    public function test_categoria_con_descendientes_activos_no_se_desactiva(): void
    {
        $padre = $this->crearCategoria();

        $this->crearCategoria('Infantiles', $padre->id);

        $this->expectException(ValidationException::class);

        $this->categorias()->cambiarEstado($padre, 'inactiva');
    }

    public function test_categoria_sin_dependencias_cambia_de_estado(): void
    {
        $categoria = $this->crearCategoria();

        $this->categorias()->cambiarEstado($categoria, 'inactiva');

        $this->assertSame('inactiva', $categoria->fresh()->estado);

        $this->categorias()->cambiarEstado($categoria, 'activa');

        $this->assertSame('activa', $categoria->fresh()->estado);
    }

    public function test_categoria_rechaza_padre_inactivo(): void
    {
        $padre = $this->crearCategoria();

        $this->categorias()->cambiarEstado($padre, 'inactiva');

        $this->expectException(ValidationException::class);

        $this->crearCategoria('Infantiles', $padre->id);
    }

    public function test_padres_disponibles_excluyen_ancestros_inactivos(): void
    {
        $padre = $this->crearCategoria();
        $hija = $this->crearCategoria('Infantiles', $padre->id);

        // Simular una jerarquia inconsistente exclusivamente en pruebas.
        $padre->update(['estado' => 'inactiva']);

        $disponibles = $this->categorias()->obtenerPadresDisponibles();

        $this->assertFalse($disponibles->contains('id', $padre->id));
        $this->assertFalse($disponibles->contains('id', $hija->id));
    }

    public function test_busqueda_categoria_interpreta_porcentaje_literalmente(): void
    {
        $this->crearCategoria('Anillos');
        $especial = $this->crearCategoria('Coleccion 100%');

        $resultado = $this->categorias()->listar(['buscar' => '%']);

        $this->assertSame(1, $resultado->total());
        $this->assertEquals($especial->id, $resultado->items()[0]->id);
    }

    public function test_metal_se_registra_activo(): void
    {
        $metal = $this->crearMetal();

        $this->assertSame('activo', $metal->estado);
        $this->assertSame('MP', $metal->simbolo);

        $this->assertDatabaseHas('metales', [
            'id' => $metal->id,
            'nombre' => 'Metal de prueba',
            'estado' => 'activo',
        ]);
    }

    public function test_metal_rechaza_nombre_duplicado(): void
    {
        $this->crearMetal('Metal de prueba');

        $this->expectException(ValidationException::class);

        $this->crearMetal('metal de prueba');
    }

    public function test_metal_sin_referencias_permite_editar_identidad(): void
    {
        $metal = $this->crearMetal();

        $actualizado = $this->metales()->actualizar($metal, [
            'nombre' => 'Metal actualizado',
            'simbolo' => 'MA',
            'descripcion' => 'Descripcion actualizada',
        ]);

        $this->assertSame('Metal actualizado', $actualizado->nombre);
        $this->assertSame('MA', $actualizado->simbolo);
    }

    public function test_metal_referenciado_protege_nombre(): void
    {
        $metal = $this->crearMetal();

        $this->crearPureza($metal);

        $this->assertTrue($this->metales()->tieneReferencias($metal));

        $this->expectException(ValidationException::class);

        $this->metales()->actualizar($metal, [
            'nombre' => 'Otro metal',
            'simbolo' => $metal->simbolo,
            'descripcion' => null,
        ]);
    }

    public function test_metal_referenciado_protege_simbolo(): void
    {
        $metal = $this->crearMetal();

        $this->crearPureza($metal);

        $this->expectException(ValidationException::class);

        $this->metales()->actualizar($metal, [
            'nombre' => $metal->nombre,
            'simbolo' => 'XX',
            'descripcion' => null,
        ]);
    }

    public function test_metal_referenciado_permite_editar_descripcion(): void
    {
        $metal = $this->crearMetal();

        $this->crearPureza($metal);

        $actualizado = $this->metales()->actualizar($metal, [
            'nombre' => $metal->nombre,
            'simbolo' => $metal->simbolo,
            'descripcion' => 'Nueva descripcion',
        ]);

        $this->assertSame('Nueva descripcion', $actualizado->descripcion);
    }

    public function test_metal_con_solo_purezas_permite_desactivacion(): void
    {
        $metal = $this->crearMetal();
        $pureza = $this->crearPureza($metal);

        $this->metales()->cambiarEstado($metal, 'inactivo');

        $this->assertSame('inactivo', $metal->fresh()->estado);
        $this->assertDatabaseHas('purezas', ['id' => $pureza->id]);
    }

    public function test_busqueda_metal_interpreta_guion_bajo_literalmente(): void
    {
        $this->crearMetal('Metal normal', 'MN');
        $especial = $this->crearMetal('Metal especial', 'M_N');

        $resultado = $this->metales()->listar(['buscar' => '_']);

        $this->assertSame(1, $resultado->total());
        $this->assertEquals($especial->id, $resultado->items()[0]->id);
    }

    public function test_pureza_normaliza_codigo(): void
    {
        $metal = $this->crearMetal();
        $pureza = $this->crearPureza($metal);

        $this->assertSame('10K', $pureza->codigo);
        $this->assertEquals($metal->id, $pureza->metal_id);
    }

    public function test_pureza_rechaza_codigo_duplicado_en_mismo_metal(): void
    {
        $metal = $this->crearMetal();

        $this->crearPureza($metal);

        $this->expectException(ValidationException::class);

        $this->purezas()->crear([
            'metal_id' => $metal->id,
            'codigo' => '10K',
            'nombre' => 'Otra pureza',
        ]);
    }

    public function test_pureza_rechaza_nombre_duplicado_en_mismo_metal(): void
    {
        $metal = $this->crearMetal();

        $this->crearPureza($metal);

        $this->expectException(ValidationException::class);

        $this->purezas()->crear([
            'metal_id' => $metal->id,
            'codigo' => '14K',
            'nombre' => 'Pureza de prueba',
        ]);
    }

    public function test_pureza_permite_codigo_igual_en_distintos_metales(): void
    {
        $a = $this->crearMetal('Metal A', 'MA');
        $b = $this->crearMetal('Metal B', 'MB');

        $purezaA = $this->crearPureza($a);
        $purezaB = $this->crearPureza($b);

        $this->assertNotEquals($purezaA->id, $purezaB->id);
        $this->assertSame($purezaA->codigo, $purezaB->codigo);
    }

    public function test_pureza_rechaza_metal_inactivo(): void
    {
        $metal = $this->crearMetal();

        $this->metales()->cambiarEstado($metal, 'inactivo');

        $this->expectException(ValidationException::class);

        $this->crearPureza($metal);
    }

    public function test_pureza_sin_referencias_permite_edicion(): void
    {
        $metal = $this->crearMetal();
        $pureza = $this->crearPureza($metal);

        $actualizada = $this->purezas()->actualizar($pureza, [
            'metal_id' => $metal->id,
            'codigo' => '14k',
            'nombre' => 'Pureza actualizada',
        ]);

        $this->assertSame('14K', $actualizada->codigo);
        $this->assertSame('Pureza actualizada', $actualizada->nombre);
    }

    public function test_pureza_de_metal_inactivo_no_esta_disponible(): void
    {
        $metal = $this->crearMetal();
        $pureza = $this->crearPureza($metal);

        $this->metales()->cambiarEstado($metal, 'inactivo');

        $disponibles = $this->purezas()->obtenerDisponibles();

        $this->assertFalse($disponibles->contains('id', $pureza->id));
    }

    public function test_busqueda_pureza_interpreta_porcentaje_literalmente(): void
    {
        $metal = $this->crearMetal();

        $this->crearPureza($metal);

        $especial = $this->purezas()->crear([
            'metal_id' => $metal->id,
            'codigo' => '99%',
            'nombre' => 'Pureza especial',
        ]);

        $resultado = $this->purezas()->listar(['buscar' => '%']);

        $this->assertSame(1, $resultado->total());
        $this->assertEquals($especial->id, $resultado->items()[0]->id);
    }

    public function test_invitado_no_puede_consultar_catalogos(): void
    {
        $this->get(route('catalogos.index'))
            ->assertRedirect(route('login'));

        $this->get(route('catalogos.categorias.index'))
            ->assertRedirect(route('login'));

        $this->get(route('catalogos.metales.index'))
            ->assertRedirect(route('login'));

        $this->get(route('catalogos.purezas.index'))
            ->assertRedirect(route('login'));
    }

    public function test_invitado_no_puede_registrar_catalogos(): void
    {
        $this->post(route('catalogos.categorias.store'), [
            'nombre' => 'Categoria no autorizada',
        ])->assertRedirect(route('login'));

        $this->post(route('catalogos.metales.store'), [
            'nombre' => 'Metal no autorizado',
        ])->assertRedirect(route('login'));

        $this->post(route('catalogos.purezas.store'), [
            'codigo' => '10K',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseMissing('categorias', [
            'nombre' => 'Categoria no autorizada',
        ]);

        $this->assertDatabaseMissing('metales', [
            'nombre' => 'Metal no autorizado',
        ]);
    }
}
