<?php

namespace Tests\Feature;

use App\Models\Business;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatWidgetPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_widget_shows_business_name_status_restart_and_send_button(): void
    {
        $b = Business::factory()->create(['name' => 'Glow Studio', 'slug' => 'glow']);
        $this->get('/chat/glow')->assertOk()
            ->assertSee('Chat with Glow Studio')->assertSee('Replies instantly')->assertSee('chat-restart', false)
            ->assertSee('Type a message / 输入信息')->assertDontSee('test chat');
    }

    public function test_widget_uses_the_business_colour_and_ignores_bad_values(): void
    {
        Business::factory()->create(['slug' => 'pink', 'settings' => ['accent' => '#db2777']]);
        Business::factory()->create(['slug' => 'evil', 'settings' => ['accent' => 'red;}</style><script>x</script>']]);
        $this->get('/chat/pink')->assertSee('--accent: #db2777', false);
        $this->get('/chat/evil')->assertOk()->assertDontSee('<script>x', false);
    }
}
