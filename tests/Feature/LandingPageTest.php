<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_page_has_hero_pricing_faq_and_calls_to_action(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('Turn every chat into a booking')->assertSee('Works with')
            ->assertSee('Most popular')->assertSee('Questions people ask')->assertSee('Stop losing customers')
            ->assertSee(route('signup'), false)->assertSee(route('login'), false);
    }
}
