<?php

namespace Modules\PlacesToVisit\Services;

use App\CentralLogics\Helpers;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\PlacesToVisit\Entities\Place;
use Modules\PlacesToVisit\Entities\PlacePrize;
use Modules\PlacesToVisit\Entities\PlaceVote;
use Modules\PlacesToVisit\Entities\PlaceWinner;

/**
 * The voter prize draw — layer 2 of Spots, deliberately independent of both
 * the venue race (layer 1) and the loyalty leaderboard (layer 3).
 *
 * Everyone who voted for the winning venue that week goes into one pool and
 * N are pulled at random. Voting history, rank, and timing change nothing:
 * a first-ever voter and a 30-week regular have identical odds. Keeping it
 * random is what lets the UI avoid every gambling-adjacent framing.
 */
class PrizeDrawService
{
    /**
     * Draw the week's voter prizes for a crowned venue.
     *
     * Only the overall champion draws — zone champions keep the trophy, the
     * banner and the Hall of Fame entry, but no voucher.
     *
     * @return Collection<PlacePrize> prizes created (empty when skipped)
     */
    public function drawFor(PlaceWinner $winner): Collection
    {
        if (!config('placestovisit.prize.enabled', true)) {
            return collect();
        }

        // Overall champion only (zone_id null)
        if ($winner->zone_id !== null) {
            return collect();
        }

        // Idempotency. This is load-bearing: WinnerService::closePeriod() is
        // lazily invoked on every read of the public winners endpoints, so
        // this method runs far more often than the weekly cron implies.
        if (PlacePrize::forPeriod($winner->period)->exists()) {
            return collect();
        }

        $userIds = $this->eligiblePool($winner);

        if ($userIds->isEmpty()) {
            return collect();
        }

        $place = Place::find($winner->place_id);
        $expiresAt = RaceClock::now()
            ->addDays((int) config('placestovisit.prize.validity_days', 7));
        $valueCap = $place?->effective_prize_value_cap;
        $currency = config('placestovisit.prize.currency', 'EGP');

        $prizes = DB::transaction(function () use ($userIds, $winner, $expiresAt, $valueCap, $currency) {
            $created = collect();

            foreach ($userIds as $userId) {
                $created->push(PlacePrize::create([
                    'period' => $winner->period,
                    'place_winner_id' => $winner->id,
                    'place_id' => $winner->place_id,
                    'user_id' => $userId,
                    'code' => $this->generateCode(),
                    'status' => PlacePrize::STATUS_ACTIVE,
                    'value_cap' => $valueCap,
                    'currency' => $currency,
                    'expires_at' => $expiresAt,
                ]));
            }

            return $created;
        });

        app(LeaderboardService::class)->clearRecentWinnersCache();

        // Push after the transaction commits — a dead FCM token must never
        // roll back an awarded prize.
        foreach ($prizes as $prize) {
            $this->notifyWinner($prize, $place);
        }

        return $prizes;
    }

    /**
     * Voters of the winning venue that week, minus flagged reviews and minus
     * anyone still inside their post-win cooldown, capped at the weekly count.
     *
     * @return Collection<int>
     */
    protected function eligiblePool(PlaceWinner $winner): Collection
    {
        $limit = max(1, (int) config('placestovisit.prize.winners_per_week', 5));
        $cooldownDays = (int) config('placestovisit.prize.winner_cooldown_days', 30);

        $query = PlaceVote::query()
            ->where('period', $winner->period)
            ->where('place_id', $winner->place_id)
            ->where('is_flagged', false)
            ->distinct();

        if ($cooldownDays > 0) {
            $recentWinnerIds = PlacePrize::where('created_at', '>=', now()->subDays($cooldownDays))
                ->pluck('user_id');

            if ($recentWinnerIds->isNotEmpty()) {
                $query->whereNotIn('user_id', $recentWinnerIds);
            }
        }

        return $query->inRandomOrder()
            ->limit($limit)
            ->pluck('user_id')
            ->unique()
            ->values();
    }

    /**
     * Human-readable, phone-shoutable voucher code: WD7K-3XQ2.
     * Ambiguous glyphs (0/O, 1/I) are excluded so a cashier retyping it from
     * a cracked phone screen doesn't fail on a lookalike.
     */
    protected function generateCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        for ($attempt = 0; $attempt < 12; $attempt++) {
            $code = collect(range(1, 8))
                ->map(fn() => $alphabet[random_int(0, strlen($alphabet) - 1)])
                ->join('');
            $code = substr($code, 0, 4) . '-' . substr($code, 4);

            if (!PlacePrize::where('code', $code)->exists()) {
                return $code;
            }
        }

        // Astronomically unlikely; fall back to something guaranteed unique
        return strtoupper(substr(Str::uuid()->toString(), 0, 4) . '-' . substr(Str::uuid()->toString(), 0, 4));
    }

    /**
     * "You won" push + the in-app notification row, mirroring the pattern in
     * App\Console\Commands\SendPrizeExpiryReminders.
     *
     * Public so a prize awarded outside the draw (admin action, the dev
     * simulator's forced win) can still notify the winner.
     *
     * @return bool whether a device push actually went out
     */
    public function notifyWinner(PlacePrize $prize, ?Place $place = null): bool
    {
        try {
            $user = User::find($prize->user_id);
            if (!$user || !$user->cm_firebase_token) {
                return false;
            }

            $place = $place ?? $prize->place;
            $venue = $place?->title ?? translate('messages.this_week_winner');

            // NOTE: send_push_notif_to_device forwards only a fixed key list
            // (order_id, trip_id, status, type, data_id, …). Anything else is
            // dropped before it reaches the device, so the prize id has to
            // travel as data_id — a `prize_id` key would never arrive.
            $data = [
                'title' => translate('messages.spots_prize_won_title'),
                'description' => translate('messages.spots_prize_won_body', ['venue' => $venue]),
                'image' => '',
                'type' => 'spots_prize_won',
                'data_id' => (string) $prize->id,
                'order_id' => '',
                'module_id' => '',
                'order_type' => '',
            ];

            Helpers::send_push_notif_to_device($user->cm_firebase_token, $data);

            DB::table('user_notifications')->insert([
                'data' => json_encode($data),
                'user_id' => $user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error("Spots prize win push failed for prize {$prize->id}: " . $e->getMessage());
            return false;
        }
    }

    // ==================== Lifecycle ====================

    /**
     * Nudge winners whose voucher dies within 24h. Stamped per prize so the
     * hourly command can't re-push the same reminder twelve times.
     */
    public function sendExpiryReminders(): int
    {
        $prizes = PlacePrize::with('place')
            ->where('status', PlacePrize::STATUS_ACTIVE)
            ->whereNull('reminder_sent_at')
            ->whereBetween('expires_at', [now(), now()->addHours(24)])
            ->get();

        $sent = 0;

        foreach ($prizes as $prize) {
            try {
                $user = User::find($prize->user_id);
                if ($user && $user->cm_firebase_token) {
                    $venue = $prize->place?->title ?? '';

                    $data = [
                        'title' => translate('messages.spots_prize_expiring_title'),
                        'description' => translate('messages.spots_prize_expiring_body', ['venue' => $venue]),
                        'image' => '',
                        'type' => 'spots_prize_won',
                        // data_id, not prize_id — see notifyWinner()
                        'data_id' => (string) $prize->id,
                        'order_id' => '',
                        'module_id' => '',
                        'order_type' => '',
                    ];

                    Helpers::send_push_notif_to_device($user->cm_firebase_token, $data);

                    DB::table('user_notifications')->insert([
                        'data' => json_encode($data),
                        'user_id' => $user->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $sent++;
                }
            } catch (\Throwable $e) {
                Log::error("Spots prize expiry reminder failed for prize {$prize->id}: " . $e->getMessage());
            }

            // Stamped either way — a user with no token shouldn't be retried hourly
            $prize->update(['reminder_sent_at' => now()]);
        }

        return $sent;
    }

    /**
     * Close out vouchers nobody used. No reissue, no rollover, no extension.
     */
    public function expireStale(): int
    {
        return PlacePrize::where('status', PlacePrize::STATUS_ACTIVE)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->update(['status' => PlacePrize::STATUS_EXPIRED]);
    }

    // ==================== Serialization ====================

    public function toApiPayload(PlacePrize $prize): array
    {
        $place = $prize->place;

        // Deep link the QR points at: scanning it opens the venue's counter
        // page with the code already filled in, so staff only tap "Redeem".
        //
        // TRADE-OFF, deliberate: this puts the venue's redeem token inside the
        // winner's own QR, so a winner can reach the counter page themselves
        // and redeem without visiting. The alternative (code-only QR) can't be
        // scanned into anything. Rotate a venue's token from admin if a link
        // spreads. See docs — the safe version is an in-page camera scanner.
        $redeemUrl = null;
        if ($place && $place->getRawOriginal('redeem_token')) {
            $redeemUrl = $place->redeem_url . '?code=' . rawurlencode($prize->code);
        }

        return [
            'id' => $prize->id,
            'period' => $prize->period,
            'code' => $prize->code,
            'redeem_url' => $redeemUrl,
            'status' => $prize->status,
            'value_cap' => $prize->value_cap,
            'currency' => $prize->currency,
            'expires_at' => $prize->expires_at?->toIso8601String(),
            'seconds_remaining' => $prize->secondsRemaining(),
            'redeemed_at' => $prize->redeemed_at?->toIso8601String(),
            'place' => $place ? [
                'id' => $place->id,
                'title' => $place->title,
                'image' => $place->image,
                'cover_image' => $place->cover_image,
                'address' => $place->address,
                'latitude' => $place->latitude,
                'longitude' => $place->longitude,
            ] : null,
        ];
    }
}
