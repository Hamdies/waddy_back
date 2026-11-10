<?php

namespace Tests\Unit;

use App\Support\RiderToken;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

/** SEC-3, the parts that need no database. */
class RiderTokenTest extends TestCase
{
    public function test_the_stored_form_is_a_sha256_not_the_token(): void
    {
        $this->assertSame(hash('sha256', 'abc'), RiderToken::hash('abc'));
        $this->assertSame(64, strlen(RiderToken::hash(str_repeat('x', 120))));
    }

    public function test_a_session_unused_for_thirty_days_has_expired(): void
    {
        $now = Carbon::create(2026, 11, 10, 12);
        $this->assertFalse(RiderToken::idleTooLong($now->copy()->subDays(29), $now));
        $this->assertTrue(RiderToken::idleTooLong($now->copy()->subDays(31), $now));
        $this->assertFalse(RiderToken::idleTooLong(null, $now));
    }
}
