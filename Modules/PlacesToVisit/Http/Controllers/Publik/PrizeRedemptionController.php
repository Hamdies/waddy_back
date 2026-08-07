<?php

namespace Modules\PlacesToVisit\Http\Controllers\Publik;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\PlacesToVisit\Entities\Place;
use Modules\PlacesToVisit\Entities\PlacePrize;
use Modules\PlacesToVisit\Services\PrizeRedemptionService;

/**
 * The counter-side page. No login, no app install — the cashier bookmarks one
 * link and types the code off the winner's phone. The token in the URL *is*
 * the venue's credential, so a code only burns where the item is owed.
 */
class PrizeRedemptionController extends Controller
{
    public function __construct(
        protected PrizeRedemptionService $redemptionService
    ) {}

    public function show(Request $request, string $token)
    {
        $venue = $this->resolveVenue($token);
        $this->applyLocale($request);

        return view('placestovisit::public.redeem', [
            'venue' => $venue,
            'token' => $token,
            'stats' => $this->stats($venue),
            // Arrives prefilled when staff scanned the winner's QR
            'prefill' => $this->redemptionService->normalize((string) $request->query('code', '')),
        ]);
    }

    public function redeem(Request $request, string $token)
    {
        $venue = $this->resolveVenue($token);
        $this->applyLocale($request);

        $request->validate(['code' => 'required|string|max:24']);

        $result = $this->redemptionService->redeem(
            $request->input('code'),
            $venue,
            $request->ip()
        );

        return back()->with([
            'redeem_result' => $result['code'],
            'redeem_message' => $result['message'],
            'redeem_prize' => isset($result['prize']) ? [
                'code' => $result['prize']->code,
                'value_cap' => $result['prize']->value_cap,
                'currency' => $result['prize']->currency,
                'redeemed_at' => $result['prize']->redeemed_at?->format('d M Y, H:i'),
                'expires_at' => $result['prize']->expires_at?->format('D d M'),
            ] : null,
            // Keep a failed code in the field so staff can correct a typo
            // instead of retyping all eight characters.
            'redeem_attempted' => $result['code'] === 'ok' ? null : $request->input('code'),
        ]);
    }

    protected function resolveVenue(string $token): Place
    {
        return Place::where('redeem_token', $token)->firstOrFail();
    }

    /** Reassurance for the cashier that the page is live and pointed at them */
    protected function stats(Place $venue): array
    {
        return [
            'outstanding' => PlacePrize::where('place_id', $venue->id)->active()->count(),
            'redeemed_total' => PlacePrize::where('place_id', $venue->id)
                ->where('status', PlacePrize::STATUS_REDEEMED)->count(),
        ];
    }

    /** ?lang=ar flips the page; staff bookmark whichever they read */
    protected function applyLocale(Request $request): void
    {
        $lang = $request->query('lang');
        if (in_array($lang, ['en', 'ar'], true)) {
            app()->setLocale($lang);
        }
    }
}
