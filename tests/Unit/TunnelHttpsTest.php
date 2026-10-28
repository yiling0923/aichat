<?php

namespace Tests\Unit;

use App\Providers\AppServiceProvider;
use PHPUnit\Framework\TestCase;

class TunnelHttpsTest extends TestCase
{
    public function test_https_is_forced_only_for_tunnel_hosts_when_app_url_is_https(): void
    {
        $url = 'https://abc123.lhr.life/aichat/public';

        $this->assertTrue(AppServiceProvider::shouldForceHttps($url, 'abc123.lhr.life'));
        $this->assertFalse(AppServiceProvider::shouldForceHttps($url, 'localhost'));
        $this->assertFalse(AppServiceProvider::shouldForceHttps($url, '127.0.0.1'));
        $this->assertFalse(AppServiceProvider::shouldForceHttps('http://localhost/aichat/public', 'abc123.lhr.life'));
    }
}
