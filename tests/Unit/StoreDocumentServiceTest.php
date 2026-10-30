<?php

namespace Tests\Unit;

use App\Models\StoreDocument;
use App\Services\StoreDocumentService;
use Carbon\Carbon;
use Tests\TestCase;

/** What the store app's Documents screen shows for one type (MA-4). */
class StoreDocumentServiceTest extends TestCase
{
    private Carbon $today;

    protected function setUp(): void
    {
        parent::setUp();
        $this->today = Carbon::parse('2026-10-30');
    }

    private function doc(string $status, ?string $expires = null, ?string $note = null): StoreDocument
    {
        $d = new StoreDocument();
        $d->status = $status;
        $d->number = 'N-1';
        $d->expires_at = $expires;
        $d->note = $note;
        $d->created_at = $this->today;
        return $d;
    }

    private function entry(array $rows, ?array $legacy = null): array
    {
        return StoreDocumentService::entry('health_licence', $rows, $legacy, $this->today);
    }

    public function test_nothing_uploaded_is_missing(): void
    {
        $this->assertSame('missing', $this->entry([])['state']);
    }

    public function test_verified_far_from_expiry(): void
    {
        $e = $this->entry([$this->doc('verified', '2028-03-31')]);
        $this->assertSame('verified', $e['state']);
        $this->assertSame('2028-03-31', $e['expires_at']);
    }

    public function test_within_thirty_days_is_expiring_with_days_left(): void
    {
        $e = $this->entry([$this->doc('verified', '2026-11-11')]);
        $this->assertSame('expiring', $e['state']);
        $this->assertSame(12, $e['days_left']);
    }

    public function test_past_expiry_is_expired(): void
    {
        $this->assertSame('expired', $this->entry([$this->doc('verified', '2026-10-01')])['state']);
    }

    public function test_new_upload_is_in_review_and_old_one_still_counts(): void
    {
        $e = $this->entry([$this->doc('pending'), $this->doc('verified', '2026-11-08')]);
        $this->assertSame('review', $e['state']);
        $this->assertTrue($e['has_current']);
        $this->assertSame('2026-11-08', $e['expires_at']);
    }

    public function test_rejected_upload_carries_the_note(): void
    {
        $e = $this->entry([$this->doc('rejected', null, 'Blurry'), $this->doc('verified', '2028-01-01')]);
        $this->assertSame('rejected', $e['state']);
        $this->assertSame('Blurry', $e['note']);
        $this->assertTrue($e['has_current']);
    }

    public function test_rejections_before_the_current_document_are_ignored(): void
    {
        $e = $this->entry([$this->doc('verified', '2028-01-01'), $this->doc('rejected', null, 'Old')]);
        $this->assertSame('verified', $e['state']);
        $this->assertNull($e['note']);
    }

    public function test_tax_number_from_registration_counts_as_verified(): void
    {
        $e = StoreDocumentService::entry('tax_card', [], ['number' => '412', 'issued_by' => null, 'holder_name' => null, 'expires_at' => '2027-01-31'], $this->today);
        $this->assertSame('verified', $e['state']);
        $this->assertSame('412', $e['number']);
    }
}
