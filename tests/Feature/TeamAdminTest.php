<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamAdminTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private User $owner;

    private User $amy;

    private User $ben;

    protected function setUp(): void
    {
        parent::setUp();
        $this->business = Business::factory()->create();
        $this->owner = User::factory()->create(['business_id' => $this->business->id, 'role' => 'owner']);
        $this->amy = User::factory()->create(['business_id' => $this->business->id, 'role' => 'staff', 'name' => 'Amy']);
        $this->ben = User::factory()->create(['business_id' => $this->business->id, 'role' => 'staff', 'name' => 'Ben']);
    }

    public function test_the_switch_turns_an_account_on_and_off_without_leaving_the_page(): void
    {
        $this->actingAs($this->owner)->postJson("/admin/settings/team/{$this->amy->id}/active", ['active' => 0])->assertOk()->assertJson(['ok' => true, 'active' => false]);
        $this->assertFalse($this->amy->fresh()->active);
        $this->actingAs($this->owner)->postJson("/admin/settings/team/{$this->amy->id}/active", ['active' => 1])->assertOk()->assertJson(['active' => true]);
        $this->assertTrue($this->amy->fresh()->active);

        // Never yourself, never the last owner, never someone from another business, never staff.
        $this->actingAs($this->owner)->postJson("/admin/settings/team/{$this->owner->id}/active", ['active' => 0])->assertStatus(422)->assertJson(['ok' => false]);
        $this->assertTrue($this->owner->fresh()->active);
        $foreign = User::factory()->create(['business_id' => Business::factory()->create()->id, 'role' => 'staff']);
        $this->actingAs($this->owner)->postJson("/admin/settings/team/{$foreign->id}/active", ['active' => 0])->assertNotFound();
        $this->actingAs($this->amy)->postJson("/admin/settings/team/{$this->ben->id}/active", ['active' => 0])->assertForbidden();
        $this->assertTrue($this->ben->fresh()->active);
    }

    public function test_the_key_button_sets_a_new_password_directly(): void
    {
        $this->actingAs($this->owner)->post("/admin/settings/team/{$this->amy->id}/password", ['new_password' => 'brand-new-pass'])->assertRedirect()->assertSessionHas('status');
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('brand-new-pass', $this->amy->fresh()->password));
        $this->assertSame('staff', $this->amy->fresh()->role);

        $this->actingAs($this->owner)->post("/admin/settings/team/{$this->amy->id}/password", ['new_password' => 'short'])->assertSessionHasErrors('new_password');
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('brand-new-pass', $this->amy->fresh()->password));
        $this->actingAs($this->amy)->post("/admin/settings/team/{$this->ben->id}/password", ['new_password' => 'hacked-pass-1'])->assertForbidden();
    }

    public function test_bulk_actions_change_several_people_and_keep_the_safety_rules(): void
    {
        $ids = [$this->amy->id, $this->ben->id];

        $this->actingAs($this->owner)->post('/admin/settings/team/bulk', ['ids' => $ids, 'action' => 'role', 'role' => 'manager'])->assertRedirect()->assertSessionHas('status', fn ($s) => str_contains($s, '2'));
        $this->assertSame(['manager', 'manager'], [$this->amy->fresh()->role, $this->ben->fresh()->role]);

        $this->actingAs($this->owner)->post('/admin/settings/team/bulk', ['ids' => $ids, 'action' => 'deactivate'])->assertRedirect();
        $this->assertSame([false, false], [$this->amy->fresh()->active, $this->ben->fresh()->active]);
        $this->actingAs($this->owner)->post('/admin/settings/team/bulk', ['ids' => $ids, 'action' => 'activate'])->assertRedirect();
        $this->assertSame([true, true], [$this->amy->fresh()->active, $this->ben->fresh()->active]);

        $this->amy->forceFill(['permissions' => ['inbox']])->save();
        $this->actingAs($this->owner)->post('/admin/settings/team/bulk', ['ids' => $ids, 'action' => 'reset_access'])->assertRedirect();
        $this->assertNull($this->amy->fresh()->permissions);

        // Yourself (and so the last owner) is skipped and explained; a missing role is refused; other businesses are untouched.
        $r = $this->actingAs($this->owner)->post('/admin/settings/team/bulk', ['ids' => [$this->owner->id, $this->amy->id], 'action' => 'deactivate']);
        $r->assertSessionHas('status', fn ($s) => str_contains($s, '1 updated') || str_contains($s, '1 skipped'));
        $this->assertTrue($this->owner->fresh()->active);
        $this->assertFalse($this->amy->fresh()->active);
        $this->actingAs($this->owner)->post('/admin/settings/team/bulk', ['ids' => $ids, 'action' => 'role'])->assertSessionHasErrors('bulk');
        $this->actingAs($this->owner)->post('/admin/settings/team/bulk', ['ids' => [$this->owner->id], 'action' => 'role', 'role' => 'staff'])->assertSessionHas('status', fn ($s) => str_contains($s, 'skipped'));
        $this->assertSame('owner', $this->owner->fresh()->role);

        $foreign = User::factory()->create(['business_id' => Business::factory()->create()->id, 'role' => 'staff']);
        $this->actingAs($this->owner)->post('/admin/settings/team/bulk', ['ids' => [$foreign->id], 'action' => 'role', 'role' => 'owner'])->assertRedirect();
        $this->assertSame('staff', $foreign->fresh()->role);
        $this->actingAs($this->ben->fresh())->post('/admin/settings/team/bulk', ['ids' => $ids, 'action' => 'deactivate'])->assertForbidden();
    }

    public function test_the_team_page_shows_columns_filters_icons_and_the_switch(): void
    {
        $page = $this->actingAs($this->owner)->get('/admin/settings?tab=team')->assertOk()
            ->assertSee('id="tm-q"', false)->assertSee('id="bulk-form"', false)->assertSee('data-toggle', false)
            ->assertSee('data-tip="Edit"', false)->assertSee('data-tip="Change password"', false)
            ->assertSee('Access')->assertSee('Usual for the role')->assertSee('dlg xwide', false);
        $this->assertStringContainsString('disabled', substr($page->getContent(), strpos($page->getContent(), 'data-toggle')));
    }
}
