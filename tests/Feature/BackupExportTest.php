<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Order;
use App\Models\QuickReply;
use App\Models\Tag;
use App\Models\User;
use App\Support\CurrentBusiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class BackupExportTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private Business $other;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->business = Business::factory()->create(['slug' => 'bk-co', 'name' => 'Bk Co', 'timezone' => 'Asia/Kuala_Lumpur']);
        $this->other = Business::factory()->create(['slug' => 'other-co']);
        $this->owner = User::factory()->create(['business_id' => $this->business->id, 'role' => 'owner']);

        app(CurrentBusiness::class)->runAs($this->business, function () {
            $tag = Tag::create(['name' => 'VIP', 'type' => 'customer', 'color' => '#0f766e']);
            $amy = Customer::create(['name' => '=HYPERLINK("x")', 'phone' => '60123', 'score' => 'hot']);
            $amy->tags()->attach($tag->id);
            Customer::create(['name' => '林小明', 'phone' => '60456']);
            Order::create(['reference' => 'O1', 'items' => 'Facial', 'amount' => 120, 'status' => 'paid', 'ordered_at' => now(), 'customer_id' => $amy->id]);
            QuickReply::create(['title' => 'Hours', 'category' => 'Info', 'body' => ['en' => 'We open at 10', 'zh' => '十点营业']]);
            $c = Conversation::create(['channel' => 'web', 'token' => 'bk1', 'customer_id' => $amy->id]);
            $c->addMessage('in', 'customer', 'Hello there');
            $c->addMessage('out', 'staff', 'secret note', null, true);
            $c->addMessage('out', 'bot', 'Hi!');
        });
        app(CurrentBusiness::class)->runAs($this->other, fn () => Customer::create(['name' => 'Rival Customer', 'phone' => '999']));
    }

    public function test_only_owners_see_the_page_and_it_shows_counts(): void
    {
        $this->actingAs($this->owner)->get('/admin/backup')->assertOk()->assertSee('Download everything')->assertSee('Download .csv')->assertSee('2 rows');

        $staff = User::factory()->create(['business_id' => $this->business->id, 'role' => 'staff']);
        $this->actingAs($staff)->get('/admin/backup')->assertForbidden();
        $this->actingAs($staff)->get('/admin/backup/customers')->assertForbidden();
    }

    public function test_a_csv_has_only_this_business_safe_cells_and_chinese(): void
    {
        $r = $this->actingAs($this->owner)->get('/admin/backup/customers')->assertOk();
        $csv = $r->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('林小明', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);   // a spreadsheet will not run it
        $this->assertStringContainsString('VIP', $csv);
        $this->assertStringNotContainsString('Rival Customer', $csv);
    }

    public function test_messages_leave_out_private_notes_and_unknown_lists_are_404(): void
    {
        $csv = $this->actingAs($this->owner)->get('/admin/backup/messages')->streamedContent();
        $this->assertStringContainsString('Hello there', $csv);
        $this->assertStringContainsString('Hi!', $csv);
        $this->assertStringNotContainsString('secret note', $csv);

        $this->actingAs($this->owner)->get('/admin/backup/passwords')->assertNotFound();
    }

    public function test_the_zip_holds_every_list(): void
    {
        $r = $this->actingAs($this->owner)->get('/admin/backup/zip')->assertOk();
        $tmp = tempnam(sys_get_temp_dir(), 'z');
        file_put_contents($tmp, $r->baseResponse->getFile()->getContent());

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($tmp) === true);
        foreach (['customers', 'bookings', 'orders', 'conversations', 'messages', 'tasks', 'replies'] as $name) {
            $this->assertNotFalse($zip->locateName($name.'.csv'), $name.'.csv is missing');
        }
        $this->assertStringNotContainsString('Rival Customer', $zip->getFromName('customers.csv'));
        $this->assertStringContainsString('十点营业', $zip->getFromName('replies.csv'));
        $zip->close();
        unlink($tmp);
    }

    public function test_the_settings_page_links_to_it(): void
    {
        $this->actingAs($this->owner)->get('/admin/manage')->assertOk()->assertSee('Backup and export');
    }
}
