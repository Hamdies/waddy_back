<?php

namespace Tests\Unit;

use App\Services\StoreRealtime;
use Tests\TestCase;

class StoreRealtimeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb.key' => 'k', 'broadcasting.connections.reverb.secret' => 's']);
    }

    public function test_a_store_may_listen_on_its_own_channel_only(): void
    {
        $this->assertSame('k:' . hash_hmac('sha256', '123.456:private-store.34', 's'), StoreRealtime::authorize(34, '123.456', 'private-store.34'));
        $this->assertNull(StoreRealtime::authorize(34, '123.456', 'private-store.35'));
        $this->assertNull(StoreRealtime::authorize(34, '123.456', 'private-rider.34'));
        $this->assertNull(StoreRealtime::authorize(34, 'not-a-socket', 'private-store.34'));
    }

    public function test_apps_get_no_connection_details_until_the_public_host_is_set(): void
    {
        config(['broadcasting.connections.reverb.public' => ['host' => null]]);
        $this->assertNull(StoreRealtime::clientConfig());
        config(['broadcasting.connections.reverb.public' => ['host' => 'waddyapp.com', 'port' => 443, 'scheme' => 'https']]);
        $this->assertSame(['key' => 'k', 'host' => 'waddyapp.com', 'port' => 443, 'scheme' => 'https'], StoreRealtime::clientConfig());
        config(['broadcasting.default' => 'log']);
        $this->assertNull(StoreRealtime::clientConfig());
    }
}
