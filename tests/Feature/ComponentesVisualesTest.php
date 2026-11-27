<?php

namespace Tests\Feature;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class ComponentesVisualesTest extends TestCase
{
    private function renderizar(
        string $plantilla,
        array $datos = []
    ): string {
        $errores = $datos['errors'] ?? new ViewErrorBag();

        app('view')->share('errors', $errores);

        app('request')->setLaravelSession(
            app('session.store')
        );

        return Blade::render(
            $plantilla,
            array_merge([
                'errors' => $errores,
            ], $datos),
            true
        );
    }

    private function crearErrores(
        array $mensajes,
        string $bolsa = 'default'
    ): ViewErrorBag {
        $errores = new ViewErrorBag();

        $errores->put(
            $bolsa,
            new MessageBag($mensajes)
        );

        return $errores;
    }

    public function test_encabezado_muestra_titulo_descripcion_y_acciones(): void
    {
        $html = $this->renderizar(<<<'BLADE'
            <x-ui.page-header
                title="Productos"
                description="Administracion del catalogo"
            >
                <x-slot:actions>
                    <button>Nuevo producto</button>
                </x-slot:actions>
            </x-ui.page-header>
        BLADE);

        $this->assertStringContainsString(
            'js-page-title',
            $html
        );

        $this->assertStringContainsString(
            'Productos',
            $html
        );

        $this->assertStringContainsString(
            'Administracion del catalogo',
            $html
        );

        $this->assertStringContainsString(
            'Nuevo producto',
            $html
        );
    }

    public function test_tarjeta_admite_contenido_acciones_y_pie(): void
    {
        $html = $this->renderizar(<<<'BLADE'
            <x-ui.card
                title="Inventario"
                description="Existencias actuales"
            >
                <x-slot:actions>
                    Actualizar
                </x-slot:actions>

                Contenido principal

                <x-slot:footer>
                    Informacion adicional
                </x-slot:footer>
            </x-ui.card>
        BLADE);

        foreach ([
            'js-card',
            'Inventario',
            'Existencias actuales',
            'Actualizar',
            'Contenido principal',
            'Informacion adicional',
        ] as $texto) {
            $this->assertStringContainsString(
                $texto,
                $html
            );
        }
    }

    public function test_boton_predeterminado_no_envia_formulario(): void
    {
        $html = $this->renderizar(<<<'BLADE'
            <x-ui.button>
                Continuar
            </x-ui.button>
        BLADE);

        $this->assertStringContainsString(
            'type="button"',
            $html
        );

        $this->assertStringContainsString(
            'js-button-primary',
            $html
        );

        $this->assertStringContainsString(
            'Continuar',
            $html
        );
    }

    public function test_boton_admite_variante_tamano_y_tipo(): void
    {
        $html = $this->renderizar(<<<'BLADE'
            <x-ui.button
                variant="danger"
                size="sm"
                type="submit"
                id="confirmar"
            >
                Confirmar
            </x-ui.button>
        BLADE);

        foreach ([
            'js-button-danger',
            'js-button-sm',
            'type="submit"',
            'id="confirmar"',
        ] as $atributo) {
            $this->assertStringContainsString(
                $atributo,
                $html
            );
        }
    }

    public function test_boton_con_enlace_genera_navegacion(): void
    {
        $html = $this->renderizar(<<<'BLADE'
            <x-ui.button
                href="/dashboard"
                variant="secondary"
            >
                Regresar
            </x-ui.button>
        BLADE);

        $this->assertStringContainsString(
            '<a',
            $html
        );

        $this->assertStringContainsString(
            'href="/dashboard"',
            $html
        );

        $this->assertStringContainsString(
            'js-button-secondary',
            $html
        );
    }

    public function test_enlace_deshabilitado_no_permite_navegacion(): void
    {
        $html = $this->renderizar(<<<'BLADE'
            <x-ui.button
                href="/productos"
                :disabled="true"
            >
                No disponible
            </x-ui.button>
        BLADE);

        $this->assertStringContainsString(
            '<button',
            $html
        );

        $this->assertMatchesRegularExpression(
            '/<button\b[^>]*\bdisabled\b/s',
            $html
        );

        $this->assertStringNotContainsString(
            'href="/productos"',
            $html
        );
    }

    public function test_alerta_muestra_variante_y_mensaje(): void
    {
        $html = $this->renderizar(<<<'BLADE'
            <x-ui.alert
                variant="danger"
                title="Operacion rechazada"
            >
                No se pudo completar la operacion.
            </x-ui.alert>
        BLADE);

        foreach ([
            'js-alert-danger',
            'role="alert"',
            'Operacion rechazada',
            'No se pudo completar la operacion.',
        ] as $texto) {
            $this->assertStringContainsString(
                $texto,
                $html
            );
        }
    }

    public function test_insignia_admite_variantes_visuales(): void
    {
        foreach ([
            'primary',
            'success',
            'warning',
            'danger',
            'info',
        ] as $variante) {
            $html = $this->renderizar(
                '<x-ui.badge :variant="$variante">
                    Estado
                </x-ui.badge>',
                ['variante' => $variante]
            );

            $this->assertStringContainsString(
                'js-badge-' . $variante,
                $html
            );

            $this->assertStringContainsString(
                'Estado',
                $html
            );
        }
    }

    public function test_variantes_desconocidas_utilizan_valores_seguros(): void
    {
        $html = $this->renderizar(<<<'BLADE'
            <x-ui.button variant="desconocida">
                Boton
            </x-ui.button>

            <x-ui.alert variant="desconocida">
                Alerta
            </x-ui.alert>

            <x-ui.badge variant="desconocida">
                Estado
            </x-ui.badge>
        BLADE);

        $this->assertStringContainsString(
            'js-button-primary',
            $html
        );

        $this->assertStringContainsString(
            'js-alert-info',
            $html
        );

        $this->assertStringNotContainsString(
            'js-badge-desconocida',
            $html
        );
    }

    public function test_tabla_renderiza_encabezados_y_filas(): void
    {
        $html = $this->renderizar(<<<'BLADE'
            <x-ui.table
                :headers="['Codigo', 'Nombre']"
                caption="Catalogo de productos"
            >
                <tr>
                    <td>PROD-001</td>
                    <td>Cadena de oro</td>
                </tr>
            </x-ui.table>
        BLADE);

        foreach ([
            'js-table-wrapper',
            'js-table',
            'scope="col"',
            'tabindex="0"',
            'Catalogo de productos',
            'Codigo',
            'Nombre',
            'PROD-001',
            'Cadena de oro',
        ] as $texto) {
            $this->assertStringContainsString(
                $texto,
                $html
            );
        }
    }

    public function test_estado_vacio_muestra_mensaje_y_acciones(): void
    {
        $html = $this->renderizar(<<<'BLADE'
            <x-ui.empty-state
                title="Sin productos"
                description="Registra tu primer producto"
            >
                <x-slot:actions>
                    Crear producto
                </x-slot:actions>
            </x-ui.empty-state>
        BLADE);

        foreach ([
            'js-empty-state',
            'Sin productos',
            'Registra tu primer producto',
            'Crear producto',
        ] as $texto) {
            $this->assertStringContainsString(
                $texto,
                $html
            );
        }
    }

    public function test_contenido_dinamico_se_escapa_correctamente(): void
    {
        $contenido = '<script>alert("prueba")</script>';

        $html = $this->renderizar(
            '<x-ui.page-header :title="$contenido" />',
            ['contenido' => $contenido]
        );

        $this->assertStringNotContainsString(
            '<script>',
            $html
        );

        $this->assertStringContainsString(
            '&lt;script&gt;',
            $html
        );
    }

    public function test_input_renderiza_valor_etiqueta_y_atributos(): void
    {
        $html = $this->renderizar(<<<'BLADE'
            <x-forms.input
                name="nombre"
                label="Nombre del producto"
                value="Cadena"
                help="Introduce el nombre comercial"
                maxlength="150"
                required
            />
        BLADE);

        foreach ([
            'name="nombre"',
            'id="nombre"',
            'for="nombre"',
            'value="Cadena"',
            'maxlength="150"',
            'aria-invalid="false"',
            'aria-describedby="nombre-help"',
            'Nombre del producto',
            'Introduce el nombre comercial',
        ] as $texto) {
            $this->assertStringContainsString(
                $texto,
                $html
            );
        }

        $this->assertMatchesRegularExpression(
            '/<input\b[^>]*\brequired\b/s',
            $html
        );
    }

    public function test_input_recupera_valor_anterior(): void
    {
        $this->withSession([
            '_old_input' => [
                'nombre' => 'Valor recuperado',
            ],
        ]);

        $html = $this->renderizar(<<<'BLADE'
            <x-forms.input
                name="nombre"
                value="Valor original"
            />
        BLADE);

        $this->assertStringContainsString(
            'value="Valor recuperado"',
            $html
        );

        $this->assertStringNotContainsString(
            'value="Valor original"',
            $html
        );
    }

    public function test_password_y_archivo_no_recuperan_valores(): void
    {
        $this->withSession([
            '_old_input' => [
                'password' => 'secreto-anterior',
                'archivo' => 'archivo-anterior',
            ],
        ]);

        $html = $this->renderizar(<<<'BLADE'
            <x-forms.input
                name="password"
                type="password"
                value="secreto-original"
            />

            <x-forms.input
                name="archivo"
                type="file"
                value="archivo-original"
            />
        BLADE);

        foreach ([
            'secreto-anterior',
            'secreto-original',
            'archivo-anterior',
            'archivo-original',
        ] as $valor) {
            $this->assertStringNotContainsString(
                $valor,
                $html
            );
        }

        $this->assertStringContainsString(
            'type="password"',
            $html
        );

        $this->assertStringContainsString(
            'type="file"',
            $html
        );
    }

    public function test_input_muestra_error_y_atributos_de_accesibilidad(): void
    {
        $errores = $this->crearErrores([
            'codigo' => [
                'El codigo ya existe.',
            ],
        ]);

        $html = $this->renderizar(
            <<<'BLADE'
                <x-forms.input
                    name="codigo"
                    label="Codigo"
                />
            BLADE,
            ['errors' => $errores]
        );

        foreach ([
            'js-input-error',
            'aria-invalid="true"',
            'aria-describedby="codigo-error"',
            'id="codigo-error"',
            'El codigo ya existe.',
        ] as $texto) {
            $this->assertStringContainsString(
                $texto,
                $html
            );
        }
    }

    public function test_input_admite_campos_anidados(): void
    {
        $this->withSession([
            '_old_input' => [
                'productos' => [
                    ['cantidad' => '5'],
                ],
            ],
        ]);

        $errores = $this->crearErrores([
            'productos.0.cantidad' => [
                'Cantidad incorrecta.',
            ],
        ]);

        $html = $this->renderizar(
            <<<'BLADE'
                <x-forms.input
                    name="productos[0][cantidad]"
                    type="number"
                />
            BLADE,
            ['errors' => $errores]
        );

        foreach ([
            'name="productos[0][cantidad]"',
            'id="productos-0-cantidad"',
            'value="5"',
            'aria-invalid="true"',
            'Cantidad incorrecta.',
        ] as $texto) {
            $this->assertStringContainsString(
                $texto,
                $html
            );
        }
    }

    public function test_select_muestra_opciones_y_valor_seleccionado(): void
    {
        $html = $this->renderizar(<<<'BLADE'
            <x-forms.select
                name="estado"
                :options="[
                    'activo' => 'Activo',
                    'inactivo' => 'Inactivo'
                ]"
                value="activo"
                placeholder="Seleccionar"
            />
        BLADE);

        $this->assertStringContainsString(
            'js-select',
            $html
        );

        $this->assertStringContainsString(
            'Seleccionar',
            $html
        );

        $this->assertMatchesRegularExpression(
            '/<option\s+value="activo"\s+selected\b/s',
            $html
        );

        $this->assertDoesNotMatchRegularExpression(
            '/<option\s+value="inactivo"\s+selected\b/s',
            $html
        );
    }

    public function test_select_multiple_recupera_valores_anteriores(): void
    {
        $this->withSession([
            '_old_input' => [
                'permisos' => ['1', '3'],
            ],
        ]);

        $html = $this->renderizar(<<<'BLADE'
            <x-forms.select
                name="permisos"
                :options="[
                    1 => 'Consultar',
                    2 => 'Editar',
                    3 => 'Registrar'
                ]"
                :multiple="true"
            />
        BLADE);

        $this->assertStringContainsString(
            'name="permisos[]"',
            $html
        );

        $this->assertMatchesRegularExpression(
            '/<select\b[^>]*\bmultiple\b/s',
            $html
        );

        $this->assertMatchesRegularExpression(
            '/<option\s+value="1"\s+selected\b/s',
            $html
        );

        $this->assertMatchesRegularExpression(
            '/<option\s+value="3"\s+selected\b/s',
            $html
        );

        $this->assertDoesNotMatchRegularExpression(
            '/<option\s+value="2"\s+selected\b/s',
            $html
        );
    }

    public function test_textarea_renderiza_valor_y_numero_de_filas(): void
    {
        $html = $this->renderizar(<<<'BLADE'
            <x-forms.textarea
                name="descripcion"
                label="Descripcion"
                value="Producto de prueba"
                :rows="6"
                maxlength="500"
            />
        BLADE);

        foreach ([
            'js-textarea',
            'name="descripcion"',
            'rows="6"',
            'maxlength="500"',
            'Producto de prueba',
        ] as $texto) {
            $this->assertStringContainsString(
                $texto,
                $html
            );
        }
    }

    public function test_textarea_recupera_y_escapa_valor_anterior(): void
    {
        $this->withSession([
            '_old_input' => [
                'descripcion' => '<script>prueba</script>',
            ],
        ]);

        $html = $this->renderizar(<<<'BLADE'
            <x-forms.textarea
                name="descripcion"
                value="Valor original"
            />
        BLADE);

        $this->assertStringContainsString(
            '&lt;script&gt;prueba&lt;/script&gt;',
            $html
        );

        $this->assertStringNotContainsString(
            '<script>',
            $html
        );

        $this->assertStringNotContainsString(
            'Valor original',
            $html
        );
    }

    public function test_checkbox_envia_valor_booleano_desmarcado(): void
    {
        $html = $this->renderizar(<<<'BLADE'
            <x-forms.checkbox
                name="es_principal"
                label="Sucursal principal"
                :checked="true"
            />
        BLADE);

        $this->assertMatchesRegularExpression(
            '/<input\s+type="hidden"\s+name="es_principal"\s+value="0"/s',
            $html
        );

        $this->assertMatchesRegularExpression(
            '/<input\b[^>]*type="checkbox"[^>]*\bchecked\b/s',
            $html
        );

        $this->assertStringContainsString(
            'Sucursal principal',
            $html
        );
    }

    public function test_checkbox_deshabilitado_no_genera_campo_oculto(): void
    {
        $html = $this->renderizar(<<<'BLADE'
            <x-forms.checkbox
                name="es_principal"
                :disabled="true"
            />
        BLADE);

        $this->assertStringNotContainsString(
            'type="hidden"',
            $html
        );

        $this->assertMatchesRegularExpression(
            '/<input\b[^>]*type="checkbox"[^>]*\bdisabled\b/s',
            $html
        );
    }

    public function test_checkbox_respeta_valor_anterior_desmarcado(): void
    {
        $this->withSession([
            '_old_input' => [
                'es_principal' => '0',
            ],
        ]);

        $html = $this->renderizar(<<<'BLADE'
            <x-forms.checkbox
                name="es_principal"
                :checked="true"
            />
        BLADE);

        $this->assertDoesNotMatchRegularExpression(
            '/<input\b[^>]*type="checkbox"[^>]*\bchecked\b/s',
            $html
        );
    }

    public function test_checkbox_multiple_no_genera_valor_oculto(): void
    {
        $this->withSession([
            '_old_input' => [
                'permisos' => ['2'],
            ],
        ]);

        $html = $this->renderizar(<<<'BLADE'
            <x-forms.checkbox
                name="permisos[]"
                value="2"
                label="Editar"
            />
        BLADE);

        $this->assertStringNotContainsString(
            'type="hidden"',
            $html
        );

        $this->assertStringContainsString(
            'name="permisos[]"',
            $html
        );

        $this->assertMatchesRegularExpression(
            '/<input\b[^>]*type="checkbox"[^>]*\bchecked\b/s',
            $html
        );
    }

    public function test_error_admite_bolsa_personalizada(): void
    {
        $errores = $this->crearErrores(
            ['nombre' => ['Nombre requerido.']],
            'producto'
        );

        $html = $this->renderizar(
            <<<'BLADE'
                <x-forms.error
                    name="nombre"
                    error-bag="producto"
                />
            BLADE,
            ['errors' => $errores]
        );

        $this->assertStringContainsString(
            'Nombre requerido.',
            $html
        );

        $this->assertStringContainsString(
            'role="alert"',
            $html
        );

        $this->assertStringContainsString(
            'js-field-error',
            $html
        );
    }

    public function test_error_no_renderiza_mensaje_si_no_existe(): void
    {
        $html = $this->renderizar(<<<'BLADE'
            <x-forms.error name="nombre" />
        BLADE);

        $this->assertStringNotContainsString(
            'js-field-error',
            $html
        );
    }

    public function test_modal_renderiza_dialogo_y_accesibilidad(): void
    {
        $html = $this->renderizar(<<<'BLADE'
            <x-ui.modal
                id="confirmacion"
                title="Confirmar operacion"
                description="Revisa la informacion"
                size="lg"
                :close-on-backdrop="false"
                :close-on-escape="false"
            >
                Contenido del modal

                <x-slot:footer>
                    Confirmar
                </x-slot:footer>
            </x-ui.modal>
        BLADE);

        foreach ([
            '<dialog',
            'id="confirmacion"',
            'data-js-modal',
            'js-modal-lg',
            'aria-labelledby="confirmacion-title"',
            'aria-describedby="confirmacion-description"',
            'data-close-on-backdrop="false"',
            'data-close-on-escape="false"',
            'data-modal-close',
            'Contenido del modal',
            'js-modal-footer',
        ] as $texto) {
            $this->assertStringContainsString(
                $texto,
                $html
            );
        }
    }

    public function test_paginacion_muestra_totales_y_pagina_actual(): void
    {
        $paginador = new LengthAwarePaginator(
            collect(range(11, 20)),
            50,
            10,
            2,
            ['path' => '/productos']
        );

        $html = $this->renderizar(
            '<x-ui.pagination :paginator="$paginador" />',
            ['paginador' => $paginador]
        );

        foreach ([
            'js-pagination',
            'Mostrando',
            '<strong>11</strong>',
            '<strong>20</strong>',
            '<strong>50</strong>',
            'aria-current="page"',
            '/productos?page=1',
            '/productos?page=3',
        ] as $texto) {
            $this->assertStringContainsString(
                $texto,
                $html
            );
        }
    }

    public function test_paginacion_simple_no_requiere_total(): void
    {
        $paginador = new Paginator(
            collect(range(1, 11)),
            10,
            1,
            ['path' => '/productos']
        );

        $html = $this->renderizar(
            '<x-ui.pagination :paginator="$paginador" />',
            ['paginador' => $paginador]
        );

        $this->assertStringContainsString(
            'Anterior',
            $html
        );

        $this->assertStringContainsString(
            'Siguiente',
            $html
        );

        $this->assertStringContainsString(
            '/productos?page=2',
            $html
        );

        $this->assertStringNotContainsString(
            'aria-current="page"',
            $html
        );
    }

    public function test_paginacion_vacia_no_muestra_controles(): void
    {
        $paginador = new LengthAwarePaginator(
            collect(),
            0,
            10,
            1,
            ['path' => '/productos']
        );

        $html = $this->renderizar(
            '<x-ui.pagination :paginator="$paginador" />',
            ['paginador' => $paginador]
        );

        $this->assertStringContainsString(
            'Sin registros disponibles',
            $html
        );

        $this->assertStringNotContainsString(
            'js-pagination-controls',
            $html
        );
    }

    public function test_filtros_utilizan_get_y_admiten_campos_adicionales(): void
    {
        $html = $this->renderizar(<<<'BLADE'
            <x-ui.filters
                action="/productos"
                search-value="Cadena"
            >
                <x-forms.select
                    name="estado"
                    :options="[
                        'activo' => 'Activo',
                        'inactivo' => 'Inactivo'
                    ]"
                />
            </x-ui.filters>
        BLADE);

        foreach ([
            'method="GET"',
            'action="/productos"',
            'name="buscar"',
            'value="Cadena"',
            'name="estado"',
            'Aplicar filtros',
            'Limpiar',
            'href="/productos"',
        ] as $texto) {
            $this->assertStringContainsString(
                $texto,
                $html
            );
        }
    }

    public function test_filtros_permiten_desactivar_busqueda(): void
    {
        $html = $this->renderizar(<<<'BLADE'
            <x-ui.filters
                action="/productos"
                :search="false"
            >
                Filtros adicionales
            </x-ui.filters>
        BLADE);

        $this->assertStringNotContainsString(
            'type="search"',
            $html
        );

        $this->assertStringContainsString(
            'Filtros adicionales',
            $html
        );
    }
}