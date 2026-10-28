<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Customer;
use App\Models\StaffNotification;
use App\Models\Task;
use App\Models\User;
use App\Support\CurrentBusiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TasksPageTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private User $owner;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->business = Business::factory()->create(['timezone' => 'Asia/Kuala_Lumpur']);
        $this->owner = User::factory()->create(['business_id' => $this->business->id, 'role' => 'owner']);
        $this->staff = User::factory()->create(['business_id' => $this->business->id, 'role' => 'staff']);
    }

    private function in(callable $fn, ?Business $b = null): mixed
    {
        return app(CurrentBusiness::class)->runAs($b ?? $this->business, $fn);
    }

    private function task(string $title, $due = null, array $over = []): Task
    {
        return $this->in(fn () => Task::create($over + ['title' => $title, 'due_at' => $due]));
    }

    public function test_open_tasks_are_grouped_by_when_they_are_due_with_counts(): void
    {
        $tz = 'Asia/Kuala_Lumpur';
        $this->task('Late one', now()->subDay());
        $this->task('Later today', now($tz)->setTime(23, 59)->utc()->max(now()->addMinutes(5)));
        $this->task('Tomorrow task', now($tz)->addDay()->setTime(12, 0)->utc());
        $this->task('This week task', now($tz)->addDays(4)->setTime(12, 0)->utc());
        $this->task('Far away', now($tz)->addDays(30)->utc());
        $this->task('Someday');
        $this->task('Finished one', null, ['done_at' => now()]);

        $r = $this->actingAs($this->staff)->get('/admin/tasks')->assertOk();
        $r->assertSee('Overdue')->assertSee('Tomorrow')->assertSee('This week')->assertSee('Later')->assertSee('No date')
            ->assertSee('Open (6)')->assertSee('Done (1)')->assertSee('1 overdue')->assertSee('Late one')->assertDontSee('Finished one');
        $html = $r->getContent();
        $this->assertLessThan(strpos($html, 'Tomorrow task'), strpos($html, 'Late one'));          // overdue comes first
        $this->assertLessThan(strpos($html, 'Someday'), strpos($html, 'Far away'));                // no-date comes last

        $this->get('/admin/tasks?show=done')->assertSee('Finished one')->assertSee('Done ', false)->assertDontSee('Late one');
    }

    public function test_only_mine_and_empty_states(): void
    {
        $this->task('Mine', null, ['assigned_to' => $this->staff->id]);
        $this->task('Theirs', null, ['assigned_to' => $this->owner->id]);

        $this->actingAs($this->staff)->get('/admin/tasks?mine=1')->assertSee('Mine')->assertDontSee('Theirs')->assertSee('Open (1)');
        Task::query()->withoutGlobalScopes()->delete();
        $this->get('/admin/tasks')->assertSee('You are all caught up');
        $this->get('/admin/tasks?mine=1')->assertSee('Nothing assigned to you');
        $this->get('/admin/tasks?show=done')->assertSee('Nothing finished yet');
    }

    public function test_a_task_can_be_edited_reassigned_with_a_notice_and_deleted(): void
    {
        $t = $this->task('Call Amy', null, ['assigned_to' => $this->owner->id]);
        $this->actingAs($this->owner);

        $this->post('/admin/tasks/'.$t->id, ['title' => 'Call Amy back', 'due_at' => '2026-12-01T15:30', 'assigned_to' => $this->staff->id])->assertRedirect();
        $t->refresh();
        $this->assertSame(['Call Amy back', $this->staff->id], [$t->title, $t->assigned_to]);
        $this->assertSame('2026-12-01 15:30', $t->due_at->copy()->setTimezone('Asia/Kuala_Lumpur')->format('Y-m-d H:i'));
        $this->assertSame(1, StaffNotification::where('user_id', $this->staff->id)->where('type', 'task')->count());

        $this->post('/admin/tasks/'.$t->id, ['title' => 'Call Amy back again', 'assigned_to' => $this->staff->id])->assertRedirect();   // not reassigned: no second notice
        $this->assertSame(1, StaffNotification::where('user_id', $this->staff->id)->where('type', 'task')->count());
        $this->assertNull($t->fresh()->due_at);

        $this->post('/admin/tasks/'.$t->id, ['title' => ''])->assertSessionHasErrors('title');
        $this->post('/admin/tasks/'.$t->id.'/toggle')->assertRedirect();
        $this->assertNotNull($t->fresh()->done_at);
        $this->post('/admin/tasks/'.$t->id.'/delete')->assertRedirect();
        $this->assertNull(Task::query()->withoutGlobalScopes()->find($t->id));
    }

    public function test_other_businesses_tasks_and_people_stay_out_of_reach(): void
    {
        $other = Business::factory()->create();
        $stranger = User::factory()->create(['business_id' => $other->id, 'role' => 'owner']);
        $theirs = $this->in(fn () => Task::create(['title' => 'Secret job']), $other);
        $mine = $this->task('Mine');
        $this->actingAs($this->owner);

        $this->get('/admin/tasks')->assertDontSee('Secret job')->assertDontSee($stranger->name);
        $this->post('/admin/tasks/'.$theirs->id, ['title' => 'Hacked'])->assertNotFound();
        $this->post('/admin/tasks/'.$mine->id, ['title' => 'Mine', 'assigned_to' => $stranger->id])->assertRedirect();
        $this->assertNull($mine->fresh()->assigned_to);   // cannot hand a task to another business's person
    }

    public function test_the_page_has_the_quick_due_buttons_and_the_edit_pop_up(): void
    {
        $this->task('Has an edit button');
        $this->actingAs($this->staff)->get('/admin/tasks')->assertSee('Today 5pm')->assertSee('Tomorrow 10am')->assertSee('Next week')->assertSee('id="task-dialog"', false)->assertSee('data-edit', false);
    }
}
