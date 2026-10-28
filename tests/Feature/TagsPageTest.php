<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Tag;
use App\Models\User;
use App\Support\CurrentBusiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagsPageTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->business = Business::factory()->create();
        $this->manager = User::factory()->create(['business_id' => $this->business->id, 'role' => 'manager']);
    }

    private function in(callable $fn): mixed
    {
        return app(CurrentBusiness::class)->runAs($this->business, $fn);
    }

    public function test_the_page_shows_tabs_counts_links_and_one_click_ideas(): void
    {
        $this->in(function () {
            $vip = Tag::create(['name' => 'VIP', 'type' => 'customer', 'color' => '#ffff00']);
            $vip->customers()->attach(Customer::create(['name' => 'A', 'language' => 'en'])->id);
            $vip->customers()->attach(Customer::create(['name' => 'B', 'language' => 'en'])->id);
            Tag::create(['name' => 'Complaint', 'type' => 'chat', 'color' => '#b91c1c']);
        });

        $r = $this->actingAs($this->manager)->get('/admin/tags')->assertOk();
        $r->assertSee('People (1)')->assertSee('Chats (1)')->assertSee('2 customers')->assertSee('Not used yet')
            ->assertSee('?tag=', false)->assertSee('+ First visit')->assertDontSee('+ VIP')->assertSee('+ Price question')->assertDontSee('+ Complaint')
            ->assertSee('data-edit=', false);
        $this->assertStringContainsString('color: #15292a', $r->getContent());   // dark text on the light yellow tag
        $this->assertStringContainsString('color: #ffffff', $r->getContent());    // white text on the dark red tag
    }

    public function test_sort_by_most_used_and_the_chat_tab(): void
    {
        $this->in(function () {
            $a = Tag::create(['name' => 'Alpha', 'type' => 'customer', 'color' => '#0f766e']);
            $z = Tag::create(['name' => 'Zulu', 'type' => 'customer', 'color' => '#0f766e']);
            $z->customers()->attach(Customer::create(['name' => 'A', 'language' => 'en'])->id);
        });

        $byName = $this->actingAs($this->manager)->get('/admin/tags')->getContent();
        $this->assertLessThan(strpos($byName, 'Zulu</span>'), strpos($byName, 'Alpha</span>'));
        $byUses = $this->get('/admin/tags?sort=uses')->getContent();
        $this->assertLessThan(strpos($byUses, 'Alpha</span>'), strpos($byUses, 'Zulu</span>'));
        $this->get('/admin/tags?tab=chat&sort=junk')->assertOk();
    }

    public function test_an_idea_adds_the_tag_in_one_click_and_duplicates_are_still_refused(): void
    {
        $this->actingAs($this->manager)->post('/admin/tags', ['type' => 'chat', 'name' => 'Urgent', 'color' => '#c2410c'])->assertRedirect();
        $this->assertSame('#c2410c', Tag::query()->withoutGlobalScopes()->where('name', 'Urgent')->first()->color);
        $this->post('/admin/tags', ['type' => 'chat', 'name' => 'Urgent', 'color' => '#c2410c'])->assertSessionHasErrors('name');
        $this->post('/admin/tags', ['type' => 'chat', 'name' => 'Bad colour', 'color' => 'red'])->assertSessionHasErrors('color');
    }

    public function test_staff_cannot_open_tags_and_other_businesses_tags_stay_hidden(): void
    {
        $other = Business::factory()->create();
        app(CurrentBusiness::class)->runAs($other, fn () => Tag::create(['name' => 'Secret tag', 'type' => 'customer', 'color' => '#000000']));
        $staff = User::factory()->create(['business_id' => $this->business->id, 'role' => 'staff']);

        $this->actingAs($staff)->get('/admin/tags')->assertForbidden();
        $this->actingAs($this->manager)->get('/admin/tags')->assertOk()->assertDontSee('Secret tag');
    }
}
