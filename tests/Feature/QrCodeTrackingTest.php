<?php

namespace Tests\Feature;

use App\Models\QrCode;
use App\Models\QrCodeScan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * /q/{code}: a tracked QR scan is counted, then redirected.
 *
 * Runs on an in-memory SQLite database holding only the QR tables, built by
 * their own migration; the project's migrations cannot build the full schema
 * from empty.
 */
class QrCodeTrackingTest extends TestCase
{
    private const PHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148';
    private const ANDROID = 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 Chrome/126.0 Mobile Safari/537.36';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');
        (require database_path('migrations/2026_10_17_000002_create_qr_codes_tables.php'))->up();

        // The redirect needs none of the web stack's settings lookups.
        $this->withoutMiddleware();
    }

    private function makeCode(array $attrs = []): QrCode
    {
        return QrCode::create($attrs + [
            'code' => QrCode::newCode(),
            'name' => 'Flyer',
            'type' => 'link',
            'target_url' => 'https://waddy.app/vet-clinic/apply?utm_source=qr',
        ]);
    }

    public function test_scan_is_counted_and_redirected(): void
    {
        $qr = $this->makeCode();

        $this->get('/q/' . $qr->code, ['User-Agent' => self::PHONE])
            ->assertRedirect('https://waddy.app/vet-clinic/apply?utm_source=qr')
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private');

        $qr->refresh();
        $this->assertSame(1, $qr->scans_count);
        $this->assertNotNull($qr->last_scanned_at);
        $scan = QrCodeScan::first();
        $this->assertSame('ios', $scan->device);
        $this->assertSame(32, strlen($scan->visitor_hash));
    }

    public function test_unique_visitors_are_told_apart_without_storing_ips(): void
    {
        $qr = $this->makeCode();

        $this->get('/q/' . $qr->code, ['User-Agent' => self::PHONE, 'REMOTE_ADDR' => '41.1.1.1']);
        $this->get('/q/' . $qr->code, ['User-Agent' => self::PHONE, 'REMOTE_ADDR' => '41.1.1.1']);
        $this->call('GET', '/q/' . $qr->code, [], [], [], ['HTTP_USER_AGENT' => self::ANDROID, 'REMOTE_ADDR' => '41.2.2.2']);

        $this->assertSame(3, $qr->refresh()->scans_count);
        $this->assertSame(2, $qr->scans()->distinct()->count('visitor_hash'));
        $this->assertSame(['android' => 1, 'ios' => 2],
            $qr->scans()->selectRaw('device, count(*) as n')->groupBy('device')->orderBy('device')->pluck('n', 'device')->map(fn ($n) => (int) $n)->all());
        $this->assertFalse(QrCodeScan::where('visitor_hash', 'like', '%41.%')->exists());
    }

    public function test_link_previews_and_bots_redirect_but_are_not_counted(): void
    {
        $qr = $this->makeCode();

        foreach (['WhatsApp/2.23.20.0 A', 'facebookexternalhit/1.1', 'TelegramBot (like TwitterBot)', 'curl/8.4.0', ''] as $ua) {
            $this->get('/q/' . $qr->code, ['User-Agent' => $ua])->assertRedirect($qr->target_url);
        }
        $this->assertSame(0, $qr->refresh()->scans_count);
        $this->assertSame(0, QrCodeScan::count());
    }

    public function test_paused_and_unknown_codes_go_home_uncounted(): void
    {
        $qr = $this->makeCode(['is_active' => false]);

        $this->get('/q/' . $qr->code, ['User-Agent' => self::PHONE])->assertRedirect(route('home'));
        $this->get('/q/Nope42', ['User-Agent' => self::PHONE])->assertRedirect(route('home'));

        $this->assertSame(0, QrCodeScan::count());
    }

    public function test_destination_can_change_after_printing(): void
    {
        $qr = $this->makeCode();
        $printed = $qr->shortUrl();

        $qr->update(['target_url' => 'https://waddy.app/new-offer']);

        $this->assertSame($printed, $qr->fresh()->shortUrl());
        $this->get('/q/' . $qr->code, ['User-Agent' => self::PHONE])->assertRedirect('https://waddy.app/new-offer');
    }

    public function test_admin_list_and_stats_add_up(): void
    {
        $qr = $this->makeCode();
        $this->makeCode(['name' => 'Unscanned']);
        $this->get('/q/' . $qr->code, ['User-Agent' => self::PHONE, 'REMOTE_ADDR' => '41.1.1.1']);
        $this->get('/q/' . $qr->code, ['User-Agent' => self::PHONE, 'REMOTE_ADDR' => '41.1.1.1']);
        $this->call('GET', '/q/' . $qr->code, [], [], [], ['HTTP_USER_AGENT' => self::ANDROID, 'REMOTE_ADDR' => '41.2.2.2']);
        // An old scan: in the total, outside the last 7 days.
        QrCodeScan::create(['qr_code_id' => $qr->id, 'scanned_at' => now()->subDays(20), 'visitor_hash' => str_repeat('a', 32), 'device' => 'desktop']);

        $controller = app(\App\Http\Controllers\Admin\QrCodeController::class);

        $row = $controller->tracked(request())->getData()['codes']->firstWhere('id', $qr->id);
        $this->assertSame(3, (int) $row->week_scans);
        $this->assertSame(3, (int) $row->today_scans);
        $this->assertSame(3, (int) $row->unique_scans);

        $data = $controller->show($qr->fresh())->getData();
        $this->assertCount(30, $data['days']);
        $this->assertSame(3, end($data['days']));
        $this->assertSame(1, $data['days'][now()->subDays(20)->toDateString()]);
        $this->assertSame(3, $data['unique']);
        $this->assertSame(3, $data['week']);
        $this->assertSame(['android' => 1, 'desktop' => 1, 'ios' => 2], collect($data['devices'])->map(fn ($n) => (int) $n)->sortKeys()->all());
    }

    public function test_deleting_a_code_deletes_its_scans(): void
    {
        $qr = $this->makeCode();
        $this->get('/q/' . $qr->code, ['User-Agent' => self::PHONE]);
        DB::statement('PRAGMA foreign_keys = ON');

        $qr->delete();

        $this->assertSame(0, QrCodeScan::count());
    }
}
