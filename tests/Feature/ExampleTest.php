<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_inicio_redirige_invitados_al_login(): void
    {
        $this->get('/')
            ->assertRedirect(route('login'));
    }

    public function test_pagina_de_login_esta_disponible(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('JoySys');
    }

    public function test_dashboard_requiere_autenticacion(): void
    {
        $this->get(route('dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_logout_no_admite_solicitudes_get(): void
    {
        $this->get('/logout')
            ->assertStatus(405);
    }

    public function test_ruta_de_salud_esta_disponible(): void
    {
        $this->get('/up')
            ->assertOk();
    }
}