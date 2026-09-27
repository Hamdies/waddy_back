<?php

namespace App\Services;

use App\CentralLogics\Helpers;
use App\Models\BusinessSetting;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\ScratchBatch;
use App\Models\ScratchCode;
use App\Models\ScratchRange;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Physical scratch cards: a printed code, typed into the ordinary promo field.
 *
 * A card code is not a coupon until someone types it. The first valid apply
 * binds the code to that customer and mints a personal, single-use coupon
 * carrying the SAME code, so from then on the order flow treats it as any other
 * coupon (PlaceNewOrder needs no special case). The plan, with the reasoning
 * behind each rule, is waddi_user/docs/scratch_card_plan.md (SC-*).
 */
class ScratchCardService
{
    /** No 0 O 1 I L: nothing a customer can misread off a scratched card. */
    public const ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';
    public const CODE_LENGTH = 8;

    /** Wrong promo codes allowed per rolling hour before the field locks (SC-04). */
    public const USER_MISS_LIMIT = 5;
    /** Per IP it is far looser: Egyptian mobile carriers put many users behind one IP. */
    public const IP_MISS_LIMIT = 60;
    public const MISS_WINDOW_SECONDS = 3600;

    /** Custody report: a holder is judged only once their ranges hold this many winners. */
    public const REPORT_MIN_HOLDER_WINNERS = 100;

    // ==================== SETTINGS ====================

    /** Program switch (SC-14). Off blocks new batches and activation, never cards already out. */
    public static function enabled(): bool
    {
        return (string) (Helpers::get_business_settings('scratch_cards_status') ?? '1') === '1';
    }

    public static function accountCap(): int
    {
        return (int) (Helpers::get_business_settings('scratch_cards_account_cap') ?? 3);
    }

    public static function capDays(): int
    {
        return (int) (Helpers::get_business_settings('scratch_cards_cap_days') ?? 30);
    }

    public static function saveSettings(bool $enabled, int $cap, int $capDays): void
    {
        foreach ([
            'scratch_cards_status' => $enabled ? '1' : '0',
            'scratch_cards_account_cap' => (string) max(1, $cap),
            'scratch_cards_cap_days' => (string) max(1, $capDays),
        ] as $key => $value) {
            BusinessSetting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }

    /**
     * What the app's card teaser needs: are cards going into bags now, and in
     * which zones. True only with the program on AND a batch switched on and
     * still within its date; a switched-off program shows no card anywhere
     * (SC-14). `zone_ids` null means every zone.
     *
     * @return array{active: bool, zone_ids: int[]|null}
     */
    public static function inBags(): array
    {
        if (!self::enabled()) {
            return ['active' => false, 'zone_ids' => []];
        }
        $zones = ScratchBatch::where('active', true)
            ->whereDate('use_before', '>=', now()->toDateString())
            ->pluck('zone_id');
        if ($zones->isEmpty()) {
            return ['active' => false, 'zone_ids' => []];
        }
        return [
            'active' => true,
            'zone_ids' => $zones->contains(null) ? null : $zones->unique()->values()->all(),
        ];
    }

    // ==================== CODES ====================

    /** What the customer typed → the stored form: uppercase, letters and digits only. */
    public static function normalize(string $code): string
    {
        return preg_replace('/[^A-Z0-9]/', '', strtoupper($code));
    }

    /** The printed form: ABCD-EFGH. */
    public static function format(string $code): string
    {
        return substr($code, 0, 4) . '-' . substr($code, 4);
    }

    private static function randomCode(): string
    {
        $max = strlen(self::ALPHABET) - 1;
        $code = '';
        for ($i = 0; $i < self::CODE_LENGTH; $i++) {
            $code .= self::ALPHABET[random_int(0, $max)];
        }
        return $code;
    }

    /**
     * [$count] codes that exist nowhere yet: not on another card, and not as a
     * coupon code (a bound card's coupon reuses the card's code).
     */
    private static function freshCodes(int $count): array
    {
        $codes = [];
        while (count($codes) < $count) {
            $candidates = [];
            while (count($candidates) < ($count - count($codes))) {
                $candidates[self::randomCode()] = true;
            }
            $candidates = array_keys(array_diff_key($candidates, $codes));
            $taken = array_merge(
                ScratchCode::whereIn('code', $candidates)->pluck('code')->all(),
                Coupon::whereIn('code', $candidates)->pluck('code')->all(),
            );
            foreach (array_diff($candidates, $taken) as $code) {
                $codes[$code] = true;
            }
        }
        return array_keys($codes);
    }

    // ==================== GENERATION (SC-01, SC-13) ====================

    /**
     * Create a batch with an EXACT prize mix, shuffled across card numbers.
     *
     * 500 cards with 325 winners means exactly 325 winners, so the maximum cost
     * is known before printing. The shuffle uses random_int: card order must not
     * be predictable from the press sequence.
     *
     * @param array{name:string, quantity:int, free_delivery_winners:int, discount_winners:int,
     *              discount_value:float, discount_min_order:float, use_before:string, zone_id:?int} $input
     */
    public static function generate(array $input): ScratchBatch
    {
        $quantity = (int) $input['quantity'];
        $freeDelivery = (int) $input['free_delivery_winners'];
        $discount = (int) $input['discount_winners'];

        $outcomes = array_merge(
            array_fill(0, $freeDelivery, 'free_delivery'),
            array_fill(0, $discount, 'discount'),
            array_fill(0, $quantity - $freeDelivery - $discount, 'none'),
        );
        for ($i = count($outcomes) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$outcomes[$i], $outcomes[$j]] = [$outcomes[$j], $outcomes[$i]];
        }

        return DB::transaction(function () use ($input, $quantity, $freeDelivery, $discount, $outcomes) {
            $batch = ScratchBatch::create([
                'name' => $input['name'],
                'quantity' => $quantity,
                'outcome_mix' => [
                    'free_delivery' => $freeDelivery,
                    'discount' => $discount,
                    'discount_value' => (float) $input['discount_value'],
                    'discount_min_order' => (float) $input['discount_min_order'],
                ],
                'zone_id' => $input['zone_id'] ?: null,
                'active' => false,
                'use_before' => $input['use_before'],
            ]);

            $codes = self::freshCodes($freeDelivery + $discount);
            $now = now();
            $rows = [];
            foreach ($outcomes as $index => $outcome) {
                if ($outcome === 'none') {
                    continue;
                }
                $isDiscount = $outcome === 'discount';
                $rows[] = [
                    'batch_id' => $batch->id,
                    'card_no' => $index + 1,
                    'code' => array_pop($codes),
                    'outcome_type' => $outcome,
                    'value' => $isDiscount ? (float) $input['discount_value'] : 0,
                    'min_order' => $isDiscount ? (float) $input['discount_min_order'] : 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            foreach (array_chunk($rows, 500) as $chunk) {
                ScratchCode::insert($chunk);
            }

            return $batch;
        });
    }

    /**
     * Every card in the batch in card-number order, losers included, for the
     * printer. Presses print in file order, so the file is the shuffle.
     */
    public static function printRows(ScratchBatch $batch): \Generator
    {
        $winners = $batch->codes()->get()->keyBy('card_no');
        $useBefore = $batch->use_before->format('d/m/Y');
        $width = max(4, strlen((string) $batch->quantity));

        for ($no = 1; $no <= $batch->quantity; $no++) {
            $card = $winners->get($no);
            if ($card === null) {
                $result = 'no_prize';
                $en = "Not this time. The next one's yours!";
                $ar = 'المرة دي لأ.. الجاية ليك!';
            } elseif ($card->outcome_type === 'free_delivery') {
                $result = 'free_delivery';
                $en = 'Waddy! You won FREE DELIVERY';
                $ar = 'واضي! كسبت توصيل مجاني';
            } else {
                $value = rtrim(rtrim(number_format($card->value, 2, '.', ''), '0'), '.');
                $min = rtrim(rtrim(number_format($card->min_order, 2, '.', ''), '0'), '.');
                $result = 'discount';
                $en = "Waddy! You won EGP {$value} off" . ($card->min_order > 0 ? " (orders over EGP {$min})" : '');
                $ar = "واضي! كسبت خصم {$value} جنيه" . ($card->min_order > 0 ? " (على طلبات فوق {$min} جنيه)" : '');
            }

            yield [
                str_pad((string) $no, $width, '0', STR_PAD_LEFT),
                $batch->name,
                $result,
                $card ? self::format($card->code) : '',
                $en,
                $ar,
                $card ? $useBefore : '',
            ];
        }
    }

    // ==================== APPLY (SC-03, SC-04, SC-15) ====================

    private static function missKeys(int $userId, ?string $ip): array
    {
        return [
            ['promo-miss:u:' . $userId, self::USER_MISS_LIMIT],
            ['promo-miss:ip:' . ($ip ?? 'none'), self::IP_MISS_LIMIT],
        ];
    }

    /** Locked out of the promo field after too many wrong codes. Checked before any lookup. */
    public static function tooManyMisses(int $userId, ?string $ip): bool
    {
        foreach (self::missKeys($userId, $ip) as [$key, $limit]) {
            if (RateLimiter::tooManyAttempts($key, $limit)) {
                return true;
            }
        }
        return false;
    }

    public static function recordMiss(int $userId, ?string $ip): void
    {
        foreach (self::missKeys($userId, $ip) as [$key]) {
            RateLimiter::hit($key, self::MISS_WINDOW_SECONDS);
        }
    }

    /**
     * Bind a typed card code to [$user] and return its coupon.
     *
     * Returns null when the text is no card code at all (the caller counts a
     * miss), ['coupon' => Coupon] on success or when this user already owns it,
     * and ['error' => code, ...] otherwise. The program switch is deliberately
     * not checked: cards already handed out are honoured (§5b).
     */
    public static function bind(string $typed, User $user): ?array
    {
        $code = self::normalize($typed);
        if (strlen($code) !== self::CODE_LENGTH) {
            return null;
        }

        return DB::transaction(function () use ($code, $user) {
            /** @var ScratchCode|null $card */
            $card = ScratchCode::with('batch')->where('code', $code)->lockForUpdate()->first();
            if ($card === null) {
                return null;
            }

            if ($card->user_id !== null) {
                // Re-applying your own card never counts against the cap.
                if ($card->user_id === $user->id && $card->coupon) {
                    return ['coupon' => $card->coupon];
                }
                return ['error' => 'card_already_used'];
            }

            $batch = $card->batch;
            if (!$batch->active) {
                return ['error' => 'card_not_active'];
            }
            if ($batch->isExpired()) {
                return ['error' => 'card_expired'];
            }

            // Per-account cap: over it, refuse WITHOUT binding, so the code
            // stays free for whoever holds the card legitimately.
            $window = now()->subDays(self::capDays());
            $recent = ScratchCode::where('user_id', $user->id)
                ->where('bound_at', '>=', $window)
                ->orderBy('bound_at')
                ->pluck('bound_at');
            if ($recent->count() >= self::accountCap()) {
                return [
                    'error' => 'card_limit',
                    'available_on' => Carbon::parse($recent->first())->addDays(self::capDays())->toDateString(),
                ];
            }

            $coupon = self::mintCoupon($card, $user);
            $card->update([
                'user_id' => $user->id,
                'bound_at' => now(),
                'coupon_id' => $coupon->id,
            ]);

            return ['coupon' => $coupon];
        });
    }

    /**
     * The personal coupon a bound card is spent with. Same shape as
     * XpService::issueDiscountCoupon: visible only to this user, single use,
     * any module (null module_id), admin-funded. Its code IS the card's code.
     */
    private static function mintCoupon(ScratchCode $card, User $user): Coupon
    {
        $freeDelivery = $card->outcome_type === 'free_delivery';

        return Coupon::create([
            'title' => $freeDelivery ? 'Scratch card: free delivery' : 'Scratch card: discount',
            'code' => $card->code,
            'start_date' => now()->toDateString(),
            'expire_date' => $card->batch->use_before->toDateString(),
            'min_purchase' => $card->min_order,
            'max_discount' => $freeDelivery ? 0 : $card->value,
            'discount' => $freeDelivery ? 0 : $card->value,
            'discount_type' => 'amount',
            'coupon_type' => $freeDelivery ? 'free_delivery' : 'default',
            'limit' => 1,
            'status' => 1,
            'data' => json_encode([]),
            'total_uses' => 0,
            'module_id' => null,
            'created_by' => 'admin',
            'customer_id' => json_encode([$user->id]),
        ]);
    }

    /** The card behind a coupon, if the coupon was minted from one. */
    public static function cardFor(Coupon $coupon): ?ScratchCode
    {
        return ScratchCode::where('coupon_id', $coupon->id)->first();
    }

    // ==================== ORDER LIFECYCLE (SC-05) ====================

    /** Record which order spent a card. Called when the order is created. */
    public static function markUsed(Order $order): void
    {
        if (!$order->coupon_code || !$order->user_id || $order->is_guest) {
            return;
        }
        ScratchCode::where('code', self::normalize($order->coupon_code))
            ->where('user_id', $order->user_id)
            ->whereNull('order_id')
            ->update(['order_id' => $order->id, 'used_at' => now()]);
    }

    /**
     * Give a card back when the order that spent it is cancelled, fails or is
     * refunded. The coupon's limit counts every order carrying its code, so
     * the limit goes up by one rather than the order being forgotten. The
     * order_id match makes a second status change a no-op.
     */
    public static function release(Order $order): void
    {
        $card = ScratchCode::where('order_id', $order->id)->first();
        if ($card === null) {
            return;
        }
        DB::transaction(function () use ($card) {
            if ($card->coupon_id) {
                Coupon::where('id', $card->coupon_id)->increment('limit');
            }
            $card->update(['order_id' => null, 'used_at' => null]);
        });
    }

    // ==================== CUSTODY REPORT (SC-15) ====================

    /**
     * Per-range figures for one batch, each judged against its own zone.
     *
     * "Reached" = the winner was typed in by someone (bound); "used" = spent on
     * an order. A range is flagged for a closer look, never as proof:
     * - low: its reach rate is under half its zone's rate in this batch, and
     *   the holder's ranges (across batches) hold enough winners to mean it;
     * - few accounts: 5+ winners reached, all by one or two accounts.
     */
    public static function rangeReport(ScratchBatch $batch): array
    {
        $ranges = $batch->ranges()->with('zone')->orderBy('from_no')->get();
        $codes = $batch->codes()->get(['card_no', 'user_id', 'used_at']);

        $rows = $ranges->map(function (ScratchRange $range) use ($codes) {
            $inRange = $codes->whereBetween('card_no', [$range->from_no, $range->to_no]);
            $reached = $inRange->whereNotNull('user_id');
            return [
                'range' => $range,
                'winners' => $inRange->count(),
                'reached' => $reached->count(),
                'used' => $inRange->whereNotNull('used_at')->count(),
                'accounts' => $reached->pluck('user_id')->unique()->count(),
            ];
        });

        $zoneRates = $rows->groupBy(fn ($row) => $row['range']->zone_id ?? 0)
            ->map(function ($group) {
                $winners = $group->sum('winners');
                return $winners > 0 ? $group->sum('reached') / $winners : 0;
            });

        $holderWinners = self::holderWinners($ranges->pluck('holder_name')->unique()->all());

        return $rows->map(function ($row) use ($zoneRates, $holderWinners) {
            $range = $row['range'];
            $rate = $row['winners'] > 0 ? $row['reached'] / $row['winners'] : 0;
            $zoneRate = $zoneRates[$range->zone_id ?? 0] ?? 0;
            $enough = ($holderWinners[$range->holder_name] ?? 0) >= self::REPORT_MIN_HOLDER_WINNERS;

            $flags = [];
            if ($enough && $zoneRate > 0 && $rate < $zoneRate / 2) {
                $flags[] = 'low_vs_zone';
            }
            if ($row['reached'] >= 5 && $row['accounts'] <= 2) {
                $flags[] = 'few_accounts';
            }

            return $row + [
                'rate' => $rate,
                'zone_rate' => $zoneRate,
                'holder_winners' => $holderWinners[$range->holder_name] ?? 0,
                'flags' => $flags,
            ];
        })->all();
    }

    /** Winners across every range each holder has been given, in any batch. */
    private static function holderWinners(array $holderNames): array
    {
        $totals = [];
        ScratchRange::whereIn('holder_name', $holderNames)->get()
            ->each(function (ScratchRange $range) use (&$totals) {
                $count = ScratchCode::where('batch_id', $range->batch_id)
                    ->whereBetween('card_no', [$range->from_no, $range->to_no])
                    ->count();
                $totals[$range->holder_name] = ($totals[$range->holder_name] ?? 0) + $count;
            });
        return $totals;
    }
}
