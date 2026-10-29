<?php

namespace App\Services;

use App\Models\Discount;
use App\Models\Order;
use App\Models\Store;
use App\Models\StoreOffer;
use App\Support\AppClock;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * OF-1: offers a store runs and pays for. The store funds the discount (and
 * the delivery fee for free delivery); commission is charged on what the
 * customer pays (decision 2, 2026-10-28). One store-wide discount (% or
 * amount) and one free-delivery offer can be live at a time.
 *
 * What apps see: a live % offer is mirrored into the store's `discounts` row,
 * which every app version already shows and prices; `stores.active_offers`
 * lists the live offers for apps that know amount-off and free delivery.
 * Orders keep `store_offer_id` / `delivery_offer_id` for results and budgets.
 */
class StoreOfferService
{
    /** Orders that count for results and budgets. */
    public const COUNTED = ['pending', 'accepted', 'confirmed', 'processing', 'handover', 'picked_up', 'delivered'];

    // ---- pure rules -------------------------------------------------------

    /**
     * Whether [now] (app-local) is inside the dates and the daily hours.
     * Hours may run past midnight (22:00–02:00).
     */
    public static function windowOpen(string $startDate, string $endDate, string $startTime, string $endTime, Carbon $now): bool
    {
        $day = $now->format('Y-m-d');
        if ($day < substr($startDate, 0, 10) || $day > substr($endDate, 0, 10)) {
            return false;
        }
        $t = $now->format('H:i:s');
        $from = strlen($startTime) === 5 ? $startTime . ':00' : $startTime;
        $to = strlen($endTime) === 5 ? $endTime . ':59' : $endTime;
        return $from <= $to ? ($t >= $from && $t <= $to) : ($t >= $from || $t <= $to);
    }

    /** EGP off for an amount offer: all of it, never more than the basket. */
    public static function amountOff(float $value, float $minOrder, float $subtotal, ?float $budgetLeft = null): float
    {
        if ($subtotal <= 0 || $subtotal < $minOrder) {
            return 0.0;
        }
        $off = min($value, $subtotal);
        return round($budgetLeft === null ? $off : max(0, min($off, $budgetLeft)), 2);
    }

    // ---- live offers ------------------------------------------------------

    public static function now(): Carbon
    {
        return AppClock::now();
    }

    /** Money this offer has given away on orders that still count. */
    public static function spent(StoreOffer $offer): float
    {
        $column = $offer->type === 'free_delivery' ? 'delivery_offer_id' : 'store_offer_id';
        $amount = $offer->type === 'free_delivery' ? 'original_delivery_charge' : 'store_discount_amount';
        return (float) Order::withoutGlobalScopes()->where($column, $offer->id)->whereIn('order_status', self::COUNTED)->sum($amount);
    }

    public static function budgetLeft(StoreOffer $offer): ?float
    {
        return $offer->budget === null ? null : max(0, $offer->budget - self::spent($offer));
    }

    public static function isLive(StoreOffer $offer, ?Carbon $now = null): bool
    {
        return $offer->status === 'active'
            && self::windowOpen($offer->start_date, $offer->end_date, $offer->start_time, $offer->end_time, $now ?? self::now())
            && ($offer->budget === null || self::budgetLeft($offer) > 0);
    }

    /** The live offer of a kind (`discount` / `delivery`), or null. */
    public static function live(int $storeId, string $kind, ?Carbon $now = null): ?StoreOffer
    {
        $types = $kind === 'delivery' ? ['free_delivery'] : ['percent', 'amount'];
        $offer = StoreOffer::where('store_id', $storeId)->where('status', 'active')->whereIn('type', $types)->latest('id')->first();
        return $offer && self::isLive($offer, $now) ? $offer : null;
    }

    /** [offer, EGP off] for a live amount offer on this basket, or null. */
    public static function amountFor(Store $store, float $subtotal, ?Carbon $now = null): ?array
    {
        $offer = self::live($store->id, 'discount', $now);
        if (!$offer || $offer->type !== 'amount') {
            return null;
        }
        $off = self::amountOff($offer->value, $offer->min_order, $subtotal, self::budgetLeft($offer));
        return $off > 0 ? [$offer, $off] : null;
    }

    /** The live free-delivery offer this basket qualifies for, or null. */
    public static function freeDeliveryFor(Store $store, float $subtotal, ?Carbon $now = null): ?StoreOffer
    {
        $offer = self::live($store->id, 'delivery', $now);
        return $offer && $subtotal >= $offer->min_order ? $offer : null;
    }

    // ---- store side -------------------------------------------------------

    /**
     * Creates an offer (replacing the live one of the same kind) and
     * publishes. Returns the offer, or an error message key.
     */
    public static function create(Store $store, array $data): StoreOffer|string
    {
        if ($data['type'] === 'percent' && self::adminDiscount($store)) {
            return 'messages.offer_waddy_discount_running';
        }
        return DB::transaction(function () use ($store, $data) {
            $kind = $data['type'] === 'free_delivery' ? ['free_delivery'] : ['percent', 'amount'];
            StoreOffer::where('store_id', $store->id)->whereIn('type', $kind)->whereIn('status', ['active', 'paused'])
                ->update(['status' => 'ended', 'ended_reason' => 'replaced']);
            $offer = StoreOffer::create([
                'store_id' => $store->id,
                'type' => $data['type'],
                'value' => $data['type'] === 'free_delivery' ? 0 : (float) $data['value'],
                'min_order' => (float) ($data['min_order'] ?? 0),
                'max_discount' => $data['type'] === 'percent' && !empty($data['max_discount']) ? (float) $data['max_discount'] : null,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'start_time' => $data['start_time'] ?? '00:00:00',
                'end_time' => $data['end_time'] ?? '23:59:59',
                'budget' => !empty($data['budget']) ? (float) $data['budget'] : null,
                'status' => 'active',
            ]);
            self::publish($store);
            return $offer;
        });
    }

    public static function setStatus(StoreOffer $offer, string $status): void
    {
        if ($offer->status === 'ended') {
            return;
        }
        $offer->update(['status' => $status, 'ended_reason' => $status === 'ended' ? 'store' : null]);
        self::publish($offer->store);
    }

    /** A discount Waddy set for this store from the admin panel (still running). */
    public static function adminDiscount(Store $store): ?Discount
    {
        return Discount::where('store_id', $store->id)->whereNull('store_offer_id')
            ->whereDate('end_date', '>=', self::now()->toDateString())->first();
    }

    /**
     * Brings what apps see in line with the store's offers: the % mirror in
     * `discounts` and the `active_offers` list.
     */
    public static function publish(Store $store): void
    {
        $offers = StoreOffer::where('store_id', $store->id)->where('status', 'active')
            ->whereDate('end_date', '>=', self::now()->toDateString())->get()
            ->filter(fn (StoreOffer $o) => $o->budget === null || self::budgetLeft($o) > 0);

        $percent = $offers->firstWhere('type', 'percent');
        if ($percent) {
            Discount::updateOrInsert(['store_id' => $store->id], [
                'start_date' => $percent->start_date, 'end_date' => $percent->end_date,
                'start_time' => $percent->start_time, 'end_time' => $percent->end_time,
                'min_purchase' => $percent->min_order, 'max_discount' => $percent->max_discount ?? 0,
                'discount' => $percent->value, 'discount_type' => 'percent', 'store_offer_id' => $percent->id,
                'updated_at' => now(), 'created_at' => now(),
            ]);
        } else {
            Discount::where('store_id', $store->id)->whereNotNull('store_offer_id')->delete();
        }

        Store::withoutGlobalScopes()->where('id', $store->id)->update(['active_offers' => $offers->isEmpty() ? null : json_encode(
            $offers->map(fn (StoreOffer $o) => [
                'id' => $o->id, 'type' => $o->type, 'value' => $o->value, 'min_order' => $o->min_order,
                'max_discount' => $o->max_discount, 'start_date' => $o->start_date, 'end_date' => $o->end_date,
                'start_time' => substr($o->start_time, 0, 5), 'end_time' => substr($o->end_time, 0, 5),
            ])->values()
        )]);
    }

    /** After an order is placed: end offers whose budget it used up. */
    public static function afterOrder(Order $order): void
    {
        foreach (array_filter([$order->store_offer_id, $order->delivery_offer_id]) as $id) {
            $offer = StoreOffer::find($id);
            if ($offer && $offer->status === 'active' && $offer->budget !== null && self::budgetLeft($offer) <= 0) {
                $offer->update(['status' => 'ended', 'ended_reason' => 'budget']);
                self::publish($offer->store);
            }
        }
    }

    /**
     * What an offer brought: orders, sales, new customers (first order at
     * this store), cost to the store, average basket vs the 30 days before.
     */
    public static function results(StoreOffer $offer): array
    {
        $column = $offer->type === 'free_delivery' ? 'delivery_offer_id' : 'store_offer_id';
        $orders = Order::withoutGlobalScopes()->where($column, $offer->id)->whereIn('order_status', self::COUNTED)
            ->get(['id', 'user_id', 'order_amount', 'store_discount_amount', 'original_delivery_charge', 'created_at']);
        $cost = (float) $orders->sum($offer->type === 'free_delivery' ? 'original_delivery_charge' : 'store_discount_amount');

        $newCustomers = 0;
        $userIds = $orders->pluck('user_id')->filter()->unique();
        if ($userIds->isNotEmpty()) {
            $firstOrder = Order::withoutGlobalScopes()->where('store_id', $offer->store_id)->whereIn('user_id', $userIds)
                ->whereIn('order_status', self::COUNTED)->groupBy('user_id')->selectRaw('user_id, MIN(id) as first_id')->pluck('first_id');
            $newCustomers = $orders->whereIn('id', $firstOrder->all())->count();
        }

        $before = Order::withoutGlobalScopes()->where('store_id', $offer->store_id)->where('order_status', 'delivered')
            ->whereBetween('created_at', [Carbon::parse($offer->created_at)->subDays(30), $offer->created_at])->avg('order_amount');

        return [
            'orders' => $orders->count(),
            'sales' => round((float) $orders->sum('order_amount'), 2),
            'new_customers' => $newCustomers,
            'cost' => round($cost, 2),
            'avg_order' => $orders->count() ? round((float) $orders->avg('order_amount'), 2) : 0,
            'avg_order_before' => $before !== null ? round((float) $before, 2) : null,
            'budget_left' => self::budgetLeft($offer),
        ];
    }
}
