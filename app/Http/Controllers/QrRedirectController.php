<?php

namespace App\Http\Controllers;

use App\Models\QrCode;
use App\Models\QrCodeScan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * /q/{code}: where a tracked QR code points. Counts the scan, then sends
 * the phone on to the code's destination.
 */
class QrRedirectController extends Controller
{
    /**
     * Link previews and crawlers fetch the link without a person scanning it.
     * WhatsApp/Telegram previews matter most: people share these short links.
     */
    private const BOTS = '/bot|crawl|spider|slurp|preview|facebookexternalhit|whatsapp\/|telegram|discord|slack|skype|curl|wget|python|okhttp|headless/i';

    public function __invoke(Request $request, string $code): RedirectResponse
    {
        $qr = QrCode::where('code', $code)->first();
        // Paused or deleted codes may still be on a poster somewhere: send them home, not to an error.
        if (!$qr || !$qr->is_active) {
            return redirect()->route('home');
        }

        $ua = (string) $request->userAgent();
        if ($request->isMethod('GET') && $ua !== '' && !preg_match(self::BOTS, $ua)) {
            try {
                DB::transaction(function () use ($qr, $request, $ua) {
                    QrCodeScan::create([
                        'qr_code_id' => $qr->id,
                        'scanned_at' => now(),
                        'visitor_hash' => substr(hash('sha256', $request->ip() . '|' . $ua . '|' . config('app.key')), 0, 32),
                        'device' => QrCodeScan::deviceFrom($ua),
                    ]);
                    QrCode::whereKey($qr->id)->update([
                        'scans_count' => DB::raw('scans_count + 1'),
                        'last_scanned_at' => now(),
                    ]);
                });
            } catch (\Throwable $e) {
                // Counting must never stop the redirect.
                info('QR scan not recorded: ' . $e->getMessage());
            }
        }

        return redirect()->away($qr->target_url, 302)
            ->header('Cache-Control', 'no-store, max-age=0')
            ->header('X-Robots-Tag', 'noindex');
    }
}
