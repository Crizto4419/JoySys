<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class IntegracionVisualTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Rutas exclusivas del entorno de pruebas
    |--------------------------------------------------------------------------
    */

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')
            ->get('/__pruebas/integracion-visual/403', function () {
                abort(403);
            });
    }

    /*
    |--------------------------------------------------------------------------
    | A. Estructura del inicio de sesion
    |--------------------------------------------------------------------------
    */

    public function test_login_utiliza_layout_de_acceso(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('class="js-guest-body"', false)
            ->assertSee('class="js-guest-shell"', false)
            ->assertSee('class="js-guest-main"', false)
            ->assertSee('class="js-guest-content"', false);
    }

    public function test_login_presenta_identidad_visual(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('JoySys')
            ->assertSee('Iniciar sesión')
            ->assertSee('Sistema de gestión de joyería')
            ->assertSee('Acceso exclusivo para personal autorizado.');
    }

    public function test_login_contiene_recursos_de_vite(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('rel="stylesheet"', false)
            ->assertSee('type="module"', false);
    }

    public function test_login_contiene_selector_de_tema(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('joysys.tema', false)
            ->assertSee('data-theme-toggle', false)
            ->assertSee('prefers-color-scheme', false);
    }

    public function test_login_no_presenta_navegacion_operativa(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('class="js-sidebar"', false)
            ->assertDontSee('Módulos autorizados')
            ->assertDontSee('Sucursal operativa')
            ->assertDontSee('Cerrar sesión');
    }

    /*
    |--------------------------------------------------------------------------
    | B. Formulario y validacion
    |--------------------------------------------------------------------------
    */

    public function test_login_contiene_formulario_post_y_csrf(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('method="POST"', false)
            ->assertSee(
                'action="' . route('login.store') . '"',
                false
            )
            ->assertSee('name="_token"', false)
            ->assertSee('type="submit"', false);
    }

    public function test_login_configura_campo_usuario(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('name="username"', false)
            ->assertSee('id="username"', false)
            ->assertSee('type="text"', false)
            ->assertSee('maxlength="50"', false)
            ->assertSee('autocomplete="username"', false)
            ->assertSee('autofocus', false)
            ->assertSee('Nombre de usuario');
    }

    public function test_login_configura_campo_password(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('name="password"', false)
            ->assertSee('id="password"', false)
            ->assertSee('type="password"', false)
            ->assertSee('maxlength="72"', false)
            ->assertSee('autocomplete="current-password"', false)
            ->assertSee('Introduce tu contraseña');
    }

    public function test_login_recupera_usuario_anterior(): void
    {
        $this->withSession([
            '_old_input' => [
                'username' => 'usuario_prueba',
            ],
        ])
            ->get(route('login'))
            ->assertOk()
            ->assertSee('value="usuario_prueba"', false);
    }

    public function test_login_no_recupera_password_anterior(): void
    {
        $password = 'PasswordPruebaNoVisible123';

        $this->withSession([
            '_old_input' => [
                'username' => 'usuario_prueba',
                'password' => $password,
            ],
        ])
            ->get(route('login'))
            ->assertOk()
            ->assertSee('value="usuario_prueba"', false)
            ->assertDontSee($password);
    }

    public function test_login_presenta_errores_de_validacion(): void
    {
        $envio = $this->from(route('login'))
            ->post(route('login.store'), [
                'username' => '',
                'password' => '',
            ]);

        $envio
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['username', 'password']);

        $errores = session('errors');

        $this->assertInstanceOf(
            \Illuminate\Support\ViewErrorBag::class,
            $errores
        );

        $mensaje = $errores->first('username');

        $this->assertNotEmpty($mensaje);

        app('view')->share('errors', $errores);

        app('request')->setLaravelSession(
            app('session.store')
        );

        $html = view('auth.login', [
            'errors' => $errores,
        ])->render();

        $this->assertStringContainsString(
            'No se pudo iniciar sesi' . "\u{00F3}" . 'n',
            $html
        );

        $this->assertStringContainsString(
            e($mensaje),
            $html
        );

        $this->assertStringContainsString(
            'id="username-error"',
            $html
        );

        $this->assertStringContainsString(
            'id="password-error"',
            $html
        );

        $this->assertStringContainsString(
            'aria-invalid="true"',
            $html
        );

        $this->assertStringContainsString(
            'aria-describedby="username-error"',
            $html
        );

        $this->assertStringContainsString(
            'js-input-error',
            $html
        );

        $this->assertStringContainsString(
            'role="alert"',
            $html
        );

        $this->assertSame(
            1,
            substr_count($html, e($mensaje))
        );
    }

    public function test_login_presenta_mensajes_de_estado(): void
    {
        $this->withSession([
            'status' => 'Operación realizada correctamente.',
        ])
            ->get(route('login'))
            ->assertOk()
            ->assertSee('Operación realizada correctamente.')
            ->assertSee('role="status"', false);
    }

    public function test_login_presenta_atributos_de_accesibilidad(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('lang="es"', false)
            ->assertSee('name="viewport"', false)
            ->assertSee('href="#joysys-main"', false)
            ->assertSee('id="joysys-main"', false)
            ->assertSee('tabindex="-1"', false)
            ->assertSee('for="username"', false)
            ->assertSee('for="password"', false)
            ->assertSee('aria-invalid="false"', false);
    }

    /*
    |--------------------------------------------------------------------------
    | C. Pagina de acceso denegado
    |--------------------------------------------------------------------------
    */

    public function test_pagina_denegada_mantiene_respuesta_403(): void
    {
        $this->get('/__pruebas/integracion-visual/403')
            ->assertForbidden()
            ->assertSee('403')
            ->assertSee('Acceso denegado');
    }

    public function test_pagina_403_utiliza_estructura_compartida(): void
    {
        $this->get('/__pruebas/integracion-visual/403')
            ->assertForbidden()
            ->assertSee('class="js-guest-body"', false)
            ->assertSee('class="js-guest-shell"', false)
            ->assertSee('data-theme-toggle', false)
            ->assertSee('joysys.tema', false)
            ->assertSee('Sistema de gestión de joyería')
            ->assertDontSee('class="js-sidebar"', false);
    }

    public function test_pagina_403_presenta_login_para_invitados(): void
    {
        $this->get('/__pruebas/integracion-visual/403')
            ->assertForbidden()
            ->assertSee('Iniciar sesión')
            ->assertSee(
                'href="' . route('login') . '"',
                false
            )
            ->assertDontSee('Cerrar sesión')
            ->assertDontSee('Volver al dashboard');
    }

    public function test_pagina_403_oculta_dashboard_sin_permiso(): void
    {
        $usuario = new User();

        Gate::define(
            'joysys.permiso',
            fn (User $usuario, string $permiso): bool => false
        );

        $this->actingAs($usuario)
            ->get('/__pruebas/integracion-visual/403')
            ->assertForbidden()
            ->assertSee('Cerrar sesión')
            ->assertSee('method="POST"', false)
            ->assertSee(
                'action="' . route('logout') . '"',
                false
            )
            ->assertSee('name="_token"', false)
            ->assertDontSee('Volver al dashboard')
            ->assertDontSee('Iniciar sesión');
    }

    public function test_pagina_403_presenta_dashboard_con_permiso(): void
    {
        $usuario = new User();

        Gate::define(
            'joysys.permiso',
            fn (User $usuario, string $permiso): bool =>
                $permiso === 'dashboard.ver'
        );

        $this->actingAs($usuario)
            ->get('/__pruebas/integracion-visual/403')
            ->assertForbidden()
            ->assertSee('Volver al dashboard')
            ->assertSee(
                'href="' . route('dashboard') . '"',
                false
            )
            ->assertSee('Cerrar sesión')
            ->assertSee('name="_token"', false)
            ->assertDontSee('Iniciar sesión');
    }
}