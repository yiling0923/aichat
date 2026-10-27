<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $this->get('/admin')->assertRedirect(route('login'));
        $this->getJson('/admin/api/conversations')->assertUnauthorized();
    }

    public function test_staff_can_sign_in_and_see_the_inbox(): void
    {
        $business = Business::factory()->create();
        User::factory()->create(['email' => 'amy@example.com', 'business_id' => $business->id]);

        $this->post('/login', ['email' => 'amy@example.com', 'password' => 'password'])->assertRedirect(route('inbox'));

        $this->get('/admin')->assertOk()->assertSee($business->name);
    }

    public function test_wrong_password_is_rejected(): void
    {
        User::factory()->create(['email' => 'amy@example.com']);

        $this->from('/login')->post('/login', ['email' => 'amy@example.com', 'password' => 'wrong'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_staff_can_sign_out(): void
    {
        $business = Business::factory()->create();
        $user = User::factory()->create(['business_id' => $business->id]);

        $this->actingAs($user)->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
