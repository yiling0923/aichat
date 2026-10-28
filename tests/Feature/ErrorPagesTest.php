<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_pages_show_the_friendly_404(): void
    {
        $this->get('/no-such-page')->assertNotFound()->assertSee('Page not found')->assertSee('Go to the home page');
        $this->get('/chat/no-such-business')->assertNotFound()->assertSee('ask them for the link again');
    }
}
