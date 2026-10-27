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

        $this->post('/login', ['email' => 'amy@example.com', 'password' => 'password'])->assertRedirect(route('today'));

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

    public function test_chat_traffic_does_not_use_up_the_login_limit(): void
    {
        $business = Business::factory()->create(['slug' => 'demo']);
        User::factory()->create(['email' => 'amy@example.com', 'business_id' => $business->id]);

        for ($i = 0; $i < 12; $i++) {
            $this->postJson(route('chat.send', 'demo'), ['text' => 'hi']);
        }

        $this->post('/login', ['email' => 'amy@example.com', 'password' => 'password'])->assertRedirect(route('today'));
    }

    public function test_repeated_wrong_logins_are_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->post('/login', ['email' => 'amy@example.com', 'password' => 'wrong']);
        }

        $this->post('/login', ['email' => 'amy@example.com', 'password' => 'wrong'])->assertStatus(429);
    }
}
