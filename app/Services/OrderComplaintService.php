<?php

namespace App\Services;

use App\CentralLogics\CustomerLogic;
use App\CentralLogics\Helpers;
use App\Models\Admin;
use App\Models\AdminWallet;
use App\Models\BusinessSetting;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Refund;
use App\Models\StoreWallet;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * OD-2: a customer complains about items in a delivered order and admin
 * refunds them. The restaurant pays its share of those items and Waddy
 * gives back its commission on them (docs/store_app_plan.md, OD-2).
 *
 * Picking every item is the old whole-order refund (order → `refunded`,
 * OrderLogic::refund_order). Anything less is a partial refund handled
 * here: the order stays `delivered`, its transaction shrinks by the
 * refunded share so reports and earnings stay right, and the `refunds`
 * row records who paid what.
 */
class OrderComplaintService
{
    public const WINDOW_KEY = 'refund_window_minutes';

    private const DEFAULT_WINDOW = 120;

    public static function windowMinutes(): int
    {
        $v = (int) BusinessSetting::where('key', self::WINDOW_KEY)->value('value');
        return $v > 0 ? $v : self::DEFAULT_WINDOW;
    }

    public static function enabled(): bool
    {
        return BusinessSetting::where('key', 'refund_active_status')->value('value') == 1;
    }

    /** Until when the customer can still complain, or null when they can't. */
    public static function deadline(Order $order): ?Carbon
    {
        if (!self::enabled() || $order->is_guest || $order->order_status !== 'delivered'
            || $order->payment_status !== 'paid' || !$order->delivered) {
            return null;
        }
        if (Refund::where('order_id', $order->id)->exists()) {
            return null; // one complaint per order
        }
        $until = Carbon::parse($order->delivered)->addMinutes(self::windowMinutes());
        return $until->isFuture() ? $until : null;
    }

    /** What the customer paid for the items: the order minus delivery, tips and the service fee. */
    public static function itemsPaid(Order $order): float
    {
        return max(0.0, round((float) $order->order_amount - (float) $order->delivery_charge - (float) $order->dm_tips
            - (float) $order->additional_charge, 2));
    }

    /**
     * Prices the picked items: each line's list value scaled by what was
     * actually paid for all items, so discounts and tax are shared out.
     *
     * @param array $picked [['id' => order_detail_id, 'quantity' => n], ...]
     * @return array|string ['items' => [...], 'amount' => float, 'full' => bool], or an error message key
     */
    public static function quote(Order $order, array $picked): array|string
    {
        $details = OrderDetail::where('order_id', $order->id)->get()->keyBy('id');
        $listTotal = $details->sum(fn ($d) => (float) $d->price * $d->quantity + (float) $d->total_add_on_price);
        $paid = self::itemsPaid($order);
        if ($listTotal <= 0 || $paid <= 0) {
            return 'messages.nothing_to_refund';
        }
        $ratio = $paid / $listTotal;

        $items = [];
        $wanted = [];
        foreach ($picked as $p) {
            $id = (int) ($p['id'] ?? 0);
            $qty = (int) ($p['quantity'] ?? 0);
            $d = $details->get($id);
            if (!$d || $qty < 1 || $qty > $d->quantity || isset($wanted[$id])) {
                return 'messages.invalid_items';
            }
            $wanted[$id] = $qty;
            $line = (float) $d->price * $d->quantity + (float) $d->total_add_on_price;
            $item = is_array($d->item_details) ? $d->item_details : (json_decode($d->item_details ?? '', true) ?: []);
            $items[] = [
                'detail_id' => $id,
                'name' => (string) ($item['name'] ?? ''),
                'quantity' => $qty,
                'amount' => round($line / $d->quantity * $qty * $ratio, 2),
            ];
        }
        if (!$items) {
            return 'messages.invalid_items';
        }

        $full = $details->every(fn ($d) => ($wanted[$d->id] ?? 0) === (int) $d->quantity);
        $amount = $full ? $paid : min($paid, round(array_sum(array_column($items, 'amount')), 2));
        return ['items' => $items, 'amount' => $amount, 'full' => $full];
    }

    /** A request that refunds less than the whole order. Old requests without items are whole-order. */
    public static function isPartial(?Refund $refund, Order $order): bool
    {
        return $refund && $refund->items && (float) $refund->refund_amount < self::itemsPaid($order);
    }

    /**
     * Admin approved a partial refund: pay the customer, take the store's
     * share from its earnings and Waddy's commission from the admin wallet.
     *
     * @return array{ok: bool, message: string, manual?: bool} manual: no wallet, admin pays the customer by hand
     */
    public static function approvePartial(Order $order, Refund $refund, ?string $adminNote = null): array
    {
        $transaction = $order->transaction;
        if (!$transaction || !$order->store) {
            return ['ok' => false, 'message' => translate('messages.faield_to_create_order_transaction')];
        }

        $method = 'manual';
        try {
            DB::transaction(function () use ($order, $refund, $transaction, $adminNote, &$method) {
                // Only one approval moves money, even on a double click. A rejected
                // complaint can still be approved later.
                $won = Refund::whereKey($refund->id)->whereIn('refund_status', ['pending', 'rejected'])->update(['refund_status' => 'approved']);
                if ($won !== 1) {
                    throw new \RuntimeException('already decided');
                }

                $amount = (float) $refund->refund_amount;
                $base = max(0.01, self::itemsPaid($order));
                $share = min(1, $amount / $base);
                $storeCut = min($amount, round((float) $transaction->store_amount * $share, 2));
                $adminCut = round($amount - $storeCut, 2);

                $adminWallet = AdminWallet::firstOrNew(['admin_id' => Admin::where('role_id', 1)->first()->id]);
                $vendorWallet = StoreWallet::firstOrNew(['vendor_id' => $order->store->vendor->id]);
                $vendorWallet->total_earning = $vendorWallet->total_earning - $storeCut;
                $adminWallet->total_commission_earning = $adminWallet->total_commission_earning - $adminCut;

                // Waddy pays the customer from the money it holds. When the store
                // took the cash itself it keeps holding it: the smaller earning
                // above is what makes it owe the refunded share back. (Lowering
                // collected_cash too, as refund_order does, would let the store
                // keep the cash and be forgiven the debt.)
                if ($transaction->received_by != 'store') {
                    if ($order->delivery_man_id && $order->payment_method != 'cash_on_delivery') {
                        $adminWallet->digital_received = $adminWallet->digital_received - $amount;
                    } else {
                        $adminWallet->manual_received = $adminWallet->manual_received - $amount;
                    }
                }
                $adminWallet->save();
                $vendorWallet->save();

                $transaction->order_amount = $transaction->order_amount - $amount;
                $transaction->store_amount = $transaction->store_amount - $storeCut;
                $transaction->admin_commission = $transaction->admin_commission - $adminCut;
                $transaction->save();

                $method = self::payCustomer($order, $refund, $amount);

                $refund->forceFill([
                    'order_status' => 'partially_refunded',
                    'admin_note' => $adminNote,
                    'refund_method' => $method,
                    'store_amount' => $storeCut,
                    'admin_amount' => $adminCut,
                ])->save();

                $order->order_status = 'delivered';
                $order->refunded = now();
                $order->save();
            });
        } catch (\RuntimeException $e) {
            return ['ok' => false, 'message' => translate('messages.already_answered')];
        }

        self::tellCustomer($order, true);
        self::tellStore($order, $refund->fresh());
        return ['ok' => true, 'message' => translate('Refund approved. The store pays its share of these items'), 'manual' => $method === 'manual'];
    }

    /** Card when asked and possible (rest to the wallet), else the wallet, else manual. */
    private static function payCustomer(Order $order, Refund $refund, float $amount): string
    {
        $walletOn = BusinessSetting::where('key', 'wallet_status')->value('value') == 1
            && BusinessSetting::where('key', 'wallet_add_refund')->value('value') == 1;

        if ($refund->refund_method === 'card' && NoonRefundService::refundRequestEligible($order)) {
            $card = NoonRefundService::openForRefundRequest($order, $amount);
            if ($card) {
                $rest = round($amount - (float) $card->amount, 2);
                if ($rest > 0 && $walletOn) {
                    CustomerLogic::create_wallet_transaction($order->user_id, $rest, 'order_refund', $order->id);
                }
                return 'card';
            }
        }
        if ($walletOn) {
            CustomerLogic::create_wallet_transaction($order->user_id, $amount, 'order_refund', $order->id);
            return 'wallet';
        }
        return 'manual';
    }

    /** New complaint: the store hears about it (admin decides; this is a heads-up). */
    public static function notifyNewComplaint(Order $order, Refund $refund): void
    {
        self::tellStore($order, $refund);
    }

    private static function tellCustomer(Order $order, bool $approved): void
    {
        $token = $order->customer?->cm_firebase_token;
        if (!$token) {
            return;
        }
        $data = [
            'title' => translate('messages.order_refunded'),
            'description' => translate('messages.Your_refund_request_has_been_approved'),
            'order_id' => $order->id,
            'image' => '',
            'type' => 'order_status',
            'order_status' => $order->order_status,
        ];
        Helpers::send_push_notif_to_device($token, $data);
        DB::table('user_notifications')->insert([
            'data' => json_encode($data), 'user_id' => $order->user_id, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** The store's view of a complaint and its outcome, as pushes in its language. */
    public static function tellStore(Order $order, Refund $refund): void
    {
        if (!$order->store) {
            return;
        }
        $amount = Helpers::format_currency((float) ($refund->store_amount ?? $refund->refund_amount));
        foreach (StorePushService::tokensByLanguage($order->store) as $lang => $tokens) {
            $ar = $lang === 'ar';
            $data = match ($refund->refund_status) {
                'approved' => [
                    'title' => $ar ? "تم رد مبلغ · #{$order->id}" : "Refund approved · #{$order->id}",
                    'description' => $ar ? "وافقت ودي على شكوى العميل. خُصم $amount من أرباحك." : "Waddy approved the customer's complaint. $amount comes out of your earnings.",
                ],
                'rejected' => [
                    'title' => $ar ? "رُفضت الشكوى · #{$order->id}" : "Complaint rejected · #{$order->id}",
                    'description' => $ar ? 'رفضت ودي شكوى العميل. لا خصم من أرباحك.' : "Waddy rejected the customer's complaint. Nothing comes out of your earnings.",
                ],
                default => [
                    'title' => $ar ? "شكوى من عميل · #{$order->id}" : "Customer complaint · #{$order->id}",
                    'description' => $ar ? 'العميل أبلغ عن مشكلة في الطلب. ودي تراجعها، افتح الطلب لترى التفاصيل.' : 'The customer reported a problem with this order. Waddy is reviewing it; open the order for details.',
                ],
            };
            $data += ['order_id' => $order->id, 'image' => '', 'type' => 'order_status', 'ring' => 0];
            foreach ($tokens as $token) {
                Helpers::send_push_notif_to_device($token, $data);
            }
        }
    }

    /** The complaint as the store and customer apps show it. */
    public static function present(?Refund $refund): ?array
    {
        if (!$refund) {
            return null;
        }
        return [
            'status' => $refund->refund_status,
            'reason' => $refund->customer_reason,
            'note' => $refund->customer_note,
            'admin_note' => $refund->admin_note,
            'items' => $refund->items ?: [],
            'amount' => (float) $refund->refund_amount,
            'store_amount' => $refund->store_amount !== null ? (float) $refund->store_amount : null,
            'images' => $refund->image_full_url,
            'created_at' => $refund->created_at,
        ];
    }
}
