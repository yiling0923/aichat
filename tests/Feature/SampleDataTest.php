<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Practitioner;
use App\Models\Service;
use App\Models\User;
use App\Support\CurrentBusiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SampleDataTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private function in(callable $fn)
    {
        return app(CurrentBusiness::class)->runAs($this->business, $fn);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->business = Business::factory()->create(['slug' => 'lumiere', 'timezone' => 'Asia/Kuala_Lumpur']);
        User::factory()->create(['business_id' => $this->business->id, 'role' => 'owner']);
        $this->in(function () {
            $p = Practitioner::create(['name' => 'Dr Mei']);
            Service::create(['name' => ['en' => 'Facial', 'zh' => '护理'], 'duration_min' => 60, 'price' => 188])->practitioners()->sync([$p->id]);
            Service::create(['name' => ['en' => 'HIFU', 'zh' => 'HIFU'], 'duration_min' => 90, 'price' => 980])->practitioners()->sync([$p->id]);
        });
    }

    public function test_it_fills_the_business_and_running_it_again_does_not_double_the_data(): void
    {
        $this->artisan('aichat:sample-data', ['--customers' => 20])->assertSuccessful();
        $first = $this->in(fn () => [Customer::count(), Booking::count(), Conversation::count()]);
        $this->assertSame(20, $first[0]);
        $this->assertGreaterThan(20, $first[1]);
        $this->assertSame(16, $first[2]);

        $this->artisan('aichat:sample-data', ['--customers' => 20])->assertSuccessful();
        $this->assertSame($first, $this->in(fn () => [Customer::count(), Booking::count(), Conversation::count()]));
    }

    public function test_no_practitioner_is_ever_booked_twice_at_the_same_time(): void
    {
        $this->artisan('aichat:sample-data')->assertSuccessful();
        $this->in(function () {
            $rows = Booking::query()->where('status', '!=', 'cancelled')->get();
            foreach ($rows as $a) {
                foreach ($rows as $b) {
                    if ($a->id < $b->id && $a->practitioner_id === $b->practitioner_id) {
                        $this->assertFalse($a->starts_at < $b->ends_at && $b->starts_at < $a->ends_at, 'Overlap between bookings '.$a->id.' and '.$b->id);
                    }
                }
            }
        });
    }

    public function test_remove_clears_only_the_sample_rows(): void
    {
        $real = $this->in(fn () => Customer::create(['name' => 'Real Rita', 'phone' => '0123456789']));
        $this->artisan('aichat:sample-data')->assertSuccessful();
        $this->artisan('aichat:sample-data', ['--remove' => true])->assertSuccessful();
        $this->in(function () use ($real) {
            $this->assertSame([$real->id], Customer::query()->pluck('id')->all());
            $this->assertSame(0, Booking::count());
            $this->assertSame(0, Conversation::count());
        });
    }

    public function test_unknown_business_is_reported(): void
    {
        $this->artisan('aichat:sample-data', ['--business' => 'nope'])->assertFailed();
    }
}
