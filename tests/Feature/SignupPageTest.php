<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SignupPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_form_has_benefits_preview_and_password_helpers(): void
    {
        $this->get('/signup')->assertOk()
            ->assertSee('Start your free trial')->assertSee('Your chat link will look like')
            ->assertSee('pw-toggle', false)->assertSee('name="website"', false)->assertDontSee('Invitation code');
    }

    public function test_errors_show_next_to_their_field(): void
    {
        $r = $this->from('/signup')->post('/signup', ['business_name' => 'G', 'name' => '', 'email' => 'bad', 'password' => 'short']);
        $r->assertSessionHasErrors(['business_name', 'name', 'email', 'password', 'terms']);
        $this->followRedirects($r)->assertSee('field-err', false)->assertSee('Please accept the terms');
    }

    public function test_taken_email_offers_sign_in(): void
    {
        $this->post('/signup', ['business_name' => 'Glow', 'name' => 'Mia', 'email' => 'mia@example.com', 'password' => 'secret-pass-1', 'terms' => '1']);
        auth()->logout();
        $r = $this->from('/signup')->post('/signup', ['business_name' => 'Glow 2', 'name' => 'Mia', 'email' => 'mia@example.com', 'password' => 'secret-pass-1', 'terms' => '1']);
        $this->followRedirects($r)->assertSee('Sign in instead');
    }

    public function test_verify_page_shows_the_address(): void
    {
        $this->post('/signup', ['business_name' => 'Glow', 'name' => 'Mia', 'email' => 'mia@example.com', 'password' => 'secret-pass-1', 'terms' => '1']);
        $this->get(route('verification.notice'))->assertOk()->assertSee('mia@example.com')->assertSee('Send it again');
    }
}
