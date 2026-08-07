<?php

namespace Tests\Feature;

use Tests\TestCase;

class ErrorPageIdentityTest extends TestCase
{
    public function test_error_shell_uses_localized_copy_and_locale_metadata(): void
    {
        app()->setLocale('pt-BR');

        $response = $this->view('errors.404');

        $response->assertSee('lang="pt-BR"', false);
        $response->assertSee("P\u{00E1}gina n\u{00E3}o encontrada");
        $response->assertSee('Voltar para o TaskConnect');
        $response->assertSee('@media (prefers-color-scheme: dark)', false);
    }
}
