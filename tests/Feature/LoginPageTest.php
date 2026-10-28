<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_forgot_and_reset_pages_render_in_the_new_style(): void
    {
        $this->get('/login')->assertOk()->assertSee('Forgot password?')->assertSee('Keep me signed in')->assertSee('pw-toggle', false);
        $this->get(route('password.request'))->assertOk()->assertSee('Email me a link');
        $this->get(route('password.reset', ['token' => 'abc', 'email' => 'a@b.com']))->assertOk()->assertSee('Save password');
    }

    public function test_wrong_password_shows_the_error_under_the_email(): void
    {
        $r = $this->from('/login')->post('/login', ['email' => 'nobody@example.com', 'password' => 'wrong-pass']);
        $this->followRedirects($r)->assertSee('These details do not match our records.')->assertSee('field-err', false)->assertSee('nobody@example.com');
    }
}
