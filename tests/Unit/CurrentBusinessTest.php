<?php

namespace Tests\Unit;

use App\Models\Business;
use App\Support\CurrentBusiness;
use PHPUnit\Framework\TestCase;

class CurrentBusinessTest extends TestCase
{
    public function test_run_as_switches_business_and_restores_the_previous_one(): void
    {
        $first = new Business(['name' => 'First']);
        $first->id = 1;
        $second = new Business(['name' => 'Second']);
        $second->id = 2;

        $current = new CurrentBusiness;
        $current->set($first);

        $seenInside = $current->runAs($second, fn () => $current->id());

        $this->assertSame(2, $seenInside);
        $this->assertSame(1, $current->id());
    }

    public function test_run_as_restores_the_business_even_when_the_callback_throws(): void
    {
        $business = new Business(['name' => 'Only']);
        $business->id = 5;

        $current = new CurrentBusiness;

        try {
            $current->runAs($business, fn () => throw new \RuntimeException('boom'));
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertNull($current->get());
    }
}
