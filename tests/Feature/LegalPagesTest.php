<?php

namespace Tests\Feature;

use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    public function test_terms_page_has_sections_contents_and_a_link_to_privacy(): void
    {
        $this->get('/terms')->assertOk()->assertSee('Terms of service')->assertSee('Last updated')->assertSee('On this page')
            ->assertSee('Messaging rules')->assertSee('Plans and payment')->assertSee(route('privacy'), false)->assertSee('Starter text only');
    }

    public function test_privacy_page_covers_data_ai_and_customer_choices(): void
    {
        $this->get('/privacy')->assertOk()->assertSee('Privacy notice')->assertSee('What we store')->assertSee('AI and other services')
            ->assertSee('Customer choices')->assertSee('STOP')->assertSee(route('terms'), false);
    }
}
