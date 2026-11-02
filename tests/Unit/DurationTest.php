<?php

namespace Tests\Unit;

use App\Support\Duration;
use Tests\TestCase;

class DurationTest extends TestCase
{
    public function test_minutes_become_words_people_read_at_a_glance(): void
    {
        $this->assertSame('0 minutes', Duration::words(0));
        $this->assertSame('1 minute', Duration::words(1));
        $this->assertSame('45 minutes', Duration::words(45));
        $this->assertSame('1 hour', Duration::words(60));
        $this->assertSame('2 hours 5 minutes', Duration::words(125));
        $this->assertSame('23 hours 59 minutes', Duration::words(1439));
        $this->assertSame('1 day', Duration::words(1440));
        $this->assertSame('1 day 1 hour', Duration::words(1500));
        $this->assertSame('3 days 22 hours', Duration::words(5655));          // the number from the Inbox note
        $this->assertSame('2 days', Duration::words(2 * 1440 + 20));          // minutes are dropped once it is days
    }

    public function test_it_reads_in_chinese_too_including_old_texts_written_in_minutes(): void
    {
        app()->setLocale('zh');
        $this->assertSame('3 天 22 小时', Duration::localize('3 days 22 hours'));
        $this->assertSame('2 小时 5 分钟', Duration::text(125));
        $this->assertSame('45 分钟', Duration::localize('45 minutes'));
        $this->assertSame('5655 分钟', Duration::localize('5655 minutes'));      // older notifications keep working
        app()->setLocale('en');
        $this->assertSame('3 days 22 hours', Duration::localize('3 days 22 hours'));
    }
}
