<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use App\Support\Currencies;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrencyTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->business = Business::factory()->create(['timezone' => 'Asia/Kuala_Lumpur']);
        $this->owner = User::factory()->create(['business_id' => $this->business->id, 'role' => 'owner']);
    }

    private function save(array $fields)
    {
        return $this->actingAs($this->owner)->post('/admin/settings/profile', $fields + ['name' => 'Shop', 'timezone' => 'Asia/Kuala_Lumpur']);
    }

    private function currency(): ?string
    {
        return ((array) $this->business->fresh()->settings)['currency'] ?? null;
    }

    public function test_the_settings_page_offers_many_currencies_grouped_by_region(): void
    {
        $page = $this->actingAs($this->owner)->get('/admin/settings')->assertOk();
        foreach (['Asia-Pacific', 'Americas', 'Europe', 'Middle East and Africa', 'NT$ (Taiwan dollar)', '₩ (Korean won)', '₹ (Indian rupee)', 'A$ (Australian dollar)', 'AED (UAE dirham)', 'Other: type your own...'] as $text) {
            $page->assertSee($text, false);
        }
        $this->assertGreaterThanOrEqual(30, count(Currencies::all()));
    }

    public function test_a_listed_currency_is_saved_and_used_across_the_staff_area(): void
    {
        $this->save(['currency' => 'NT$'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('NT$', $this->currency());

        $this->actingAs($this->owner->fresh())->get('/admin/orders')->assertOk()->assertSee('NT$');
    }

    public function test_other_lets_the_owner_type_their_own_and_bad_input_is_refused(): void
    {
        $this->save(['currency' => '__other', 'currency_custom' => 'BND'])->assertSessionHasNoErrors();
        $this->assertSame('BND', $this->currency());

        // Their own currency stays in the list so saving the page again keeps it.
        $this->actingAs($this->owner)->get('/admin/settings')->assertSee('BND (your own)', false);
        $this->save(['currency' => 'BND'])->assertSessionHasNoErrors();
        $this->assertSame('BND', $this->currency());

        foreach (['<b>x</b>', 'TOOLONGONE', '"; drop', ''] as $bad) {
            $this->save(['currency' => '__other', 'currency_custom' => $bad])->assertSessionHasErrors('currency_custom');
        }
        $this->save(['currency' => 'ZZZ'])->assertSessionHasErrors('currency_custom');          // not listed and not their own
        $this->assertSame('BND', $this->currency());
    }

    public function test_only_safe_short_values_count_as_a_currency(): void
    {
        foreach (['RM', 'S$', 'BND', 'Fr', '€', 'Rs.', 'HK $'] as $ok) {
            $this->assertSame($ok, Currencies::clean($ok));
        }
        foreach (['', '   ', '<x>', 'ABCDEFG', "a\nb", 'RM"'] as $bad) {
            $this->assertNull(Currencies::clean($bad));
        }
    }

    public function test_the_platforms_own_billing_currency_comes_from_the_environment(): void
    {
        $this->assertSame('USD', config('plans.currency'));       // the default; BILLING_CURRENCY in .env changes it
        $this->assertStringContainsString('BILLING_CURRENCY', (string) file_get_contents(base_path('.env.example')));
    }
}
