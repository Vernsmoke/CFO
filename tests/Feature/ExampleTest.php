<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_landing_page_contains_the_sign_in_form(): void
    {
        $response = $this->get('/');

        $response
            ->assertStatus(200)
            ->assertSee('id="sign-in"', false)
            ->assertSee('name="login"', false)
            ->assertSee('name="password"', false)
            ->assertSee('Create Student Account')
            ->assertDontSee('nav-cta');
    }
}
