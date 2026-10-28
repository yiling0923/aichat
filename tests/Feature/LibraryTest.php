<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Conversation;
use App\Models\QuickReply;
use App\Models\User;
use App\Support\CurrentBusiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LibraryTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private User $manager;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->business = Business::factory()->create(['name' => 'Glow Clinic']);
        $this->manager = User::factory()->create(['business_id' => $this->business->id, 'role' => 'manager']);
        $this->staff = User::factory()->create(['business_id' => $this->business->id, 'role' => 'staff']);
    }

    private function reply(array $over = [], ?Business $b = null): QuickReply
    {
        return app(CurrentBusiness::class)->runAs($b ?? $this->business, fn () => QuickReply::create($over + ['title' => 'Hours', 'category' => 'Prices', 'body' => ['en' => 'We open at 10', 'zh' => '十点营业']]));
    }

    public function test_managers_save_replies_with_a_group_and_the_page_shows_groups_search_and_edit(): void
    {
        $this->actingAs($this->manager)->post('/admin/library', ['title' => 'Parking', 'category' => 'Booking', 'body_en' => 'Free for 2 hours', 'body_zh' => ''])->assertRedirect();
        $this->post('/admin/library', ['title' => 'No group', 'body_en' => 'Hello {name}'])->assertRedirect();

        $this->assertSame('Booking', QuickReply::query()->withoutGlobalScopes()->where('title', 'Parking')->first()->category);
        $this->assertNull(QuickReply::query()->withoutGlobalScopes()->where('title', 'No group')->first()->category);

        $this->get('/admin/library')->assertOk()->assertSee('Parking')->assertSee('Booking (1)', false)->assertSee('Other (1)', false)
            ->assertSee('id="lib-search"', false)->assertSee('New reply')->assertSee('data-edit', false);
    }

    public function test_staff_can_read_and_copy_but_not_edit(): void
    {
        $r = $this->reply();

        $this->actingAs($this->staff)->get('/admin/library')->assertOk()->assertSee('Hours')->assertSee('Copy English')
            ->assertDontSee('id="new-reply"', false)->assertDontSee('data-edit=', false)->assertSee('Only an owner or manager can change this list');
        $this->post('/admin/library', ['title' => 'x', 'body_en' => 'y'])->assertForbidden();
        $this->post('/admin/library/'.$r->id, ['title' => 'x', 'body_en' => 'y'])->assertForbidden();
        $this->post('/admin/library/'.$r->id.'/delete')->assertForbidden();
    }

    public function test_the_picker_data_carries_groups_and_only_this_business(): void
    {
        $this->reply(['title' => 'Mine', 'category' => 'Greeting']);
        $this->reply(['title' => 'Not mine'], Business::factory()->create());

        $list = collect($this->actingAs($this->staff)->getJson('/admin/api/quick-replies')->assertOk()->json('replies'));
        $this->assertSame(['Mine'], $list->pluck('title')->all());
        $this->assertSame('Greeting', $list->first()['category']);
        $this->assertSame('十点营业', $list->first()['body']['zh'] ?? '十点营业');
    }

    public function test_edit_and_delete_work_and_cannot_touch_other_businesses(): void
    {
        $mine = $this->reply();
        $theirs = $this->reply(['title' => 'Theirs'], Business::factory()->create());
        $this->actingAs($this->manager);

        $this->post('/admin/library/'.$mine->id, ['title' => 'Opening hours', 'category' => 'Aftercare', 'body_en' => 'Open 9', 'body_zh' => '九点'])->assertRedirect();
        $this->assertSame(['Opening hours', 'Aftercare'], [$mine->fresh()->title, $mine->fresh()->category]);

        $this->post('/admin/library/'.$theirs->id, ['title' => 'Hacked', 'body_en' => 'x'])->assertNotFound();
        $this->post('/admin/library/'.$theirs->id.'/delete')->assertNotFound();
        $this->post('/admin/library/'.$mine->id.'/delete')->assertRedirect();
        $this->assertNull(QuickReply::query()->withoutGlobalScopes()->find($mine->id));
        $this->post('/admin/library', ['title' => '', 'body_en' => ''])->assertSessionHasErrors(['title', 'body_en']);
    }

    public function test_the_inbox_knows_the_chat_language_and_business_for_filling_in_replies(): void
    {
        $chat = app(CurrentBusiness::class)->runAs($this->business, function () {
            $c = Conversation::startFor('web');
            $c->forceFill(['language' => 'zh'])->save();

            return $c;
        });

        $this->actingAs($this->staff)->getJson('/admin/api/conversations/'.$chat->id)->assertOk()->assertJsonPath('conversation.language', 'zh');
        $this->get('/admin')->assertSee('data-business="Glow Clinic"', false);
    }
}
