<?php

namespace App\Observers;

use App\Models\Order;
use App\Models\OrderReference;
use App\Models\User;
use App\Services\XpService;
use App\Services\ChallengeService;
use App\Services\ScratchCardService;
use App\Services\StorePushService;
use App\Services\EstimatedDeliveryService;
use App\Services\LateApologyService;
use Illuminate\Support\Facades\Log;

class OrderObserver
{
    /**
     * Handle the Order "created" event.
     */
    /**
     * The first ETA and the promise we hold ourselves to (XS-1, XS-6).
     * calculateInitialEstimate existed but was never called, so an order had
     * no ETA until its first status change.
     */
    public function creating(Order $order): void
    {
        if ($order->estimated_delivery_at || $order->order_type === 'pos') {
            return;
        }
        try {
            $eta = EstimatedDeliveryService::calculateInitialEstimate($order, $order->store);
            $order->estimated_delivery_at = $eta;
            $order->promised_delivery_at = $eta;
        } catch (\Throwable $e) {
            Log::warning('Initial ETA failed: ' . $e->getMessage());
        }
    }

    public function created(Order $order): void
    {
        $OrderReference = new OrderReference();
        $OrderReference->order_id = $order->id;
        $OrderReference->save();

        // A scratch card code spent on this order (SC-05).
        try {
            ScratchCardService::markUsed($order);
        } catch (\Exception $e) {
            Log::error("Failed to mark scratch card used for order {$order->id}: " . $e->getMessage());
        }
    }

    /**
     * Handle the Order "updated" event.
     * Award XP when order status changes to 'delivered'.
     */
    public function updated(Order $order): void
    {
        if (!$order->isDirty('order_status')) {
            return;
        }

        // The store's other phones stop ringing once someone dealt with it
        // (SB-1). Here, not in the controllers: admin, the web panel, the
        // app, POS and customer cancel all change status.
        try {
            StorePushService::orderStatusChanged($order, $order->getOriginal('order_status'));
        } catch (\Exception $e) {
            Log::error("Failed to send order_handled for order {$order->id}: " . $e->getMessage());
        }

        // Late against the first promise: apologise with wallet credit (XS-6).
        if ($order->order_status === 'delivered') {
            LateApologyService::handleDelivered($order);
        }

        // Award XP when an order is delivered.
        if ($order->order_status === 'delivered') {
            $this->handleOrderDelivered($order);
        }

        // Reverse XP when a previously-earning order is refunded.
        if ($order->order_status === 'refunded') {
            $this->handleOrderRefunded($order);
        }

        // Give a scratch card back when the order that spent it doesn't go through (SC-05).
        if (in_array($order->order_status, ['canceled', 'failed', 'refunded'], true)) {
            try {
                ScratchCardService::release($order);
            } catch (\Exception $e) {
                Log::error("Failed to release scratch card for order {$order->id}: " . $e->getMessage());
            }
        }
    }

    /**
     * Handle order delivery - award XP and check challenges.
     */
    protected function handleOrderDelivered(Order $order): void
    {
        // Skip guest orders
        if ($order->is_guest) {
            return;
        }

        // Get the user
        $user = User::find($order->user_id);
        if (!$user) {
            return;
        }

        try {
            Log::info("Processing XP for delivered order: order_id={$order->id}, user_id={$user->id}");

            // Award XP for order completion and amount spent
            XpService::addOrderXp($user, $order);

            // Award referral bonus to the referrer on the referred user's
            // first delivered order (guarded + deduped inside addReferralXp).
            if ($user->ref_by) {
                $referrer = User::find($user->ref_by);
                if ($referrer) {
                    XpService::addReferralXp($referrer, $user, $order);
                }
            }

            // Check and update challenge progress
            ChallengeService::checkProgress($user, $order);

            Log::info("XP processed successfully for order: {$order->id}");
        } catch (\Exception $e) {
            Log::error("Failed to process XP for order {$order->id}: " . $e->getMessage());
        }
    }

    /**
     * Handle order refund - reverse any XP earned from it.
     */
    protected function handleOrderRefunded(Order $order): void
    {
        if ($order->is_guest) {
            return;
        }

        $user = User::find($order->user_id);
        if (!$user) {
            return;
        }

        try {
            Log::info("Reversing XP for refunded order: order_id={$order->id}, user_id={$user->id}");
            XpService::reverseOrderXp($user, $order);
        } catch (\Exception $e) {
            Log::error("Failed to reverse XP for order {$order->id}: " . $e->getMessage());
        }
    }

    /**
     * Handle the Order "deleted" event.
     */
    public function deleted(Order $order): void
    {
        //
    }

    /**
     * Handle the Order "restored" event.
     */
    public function restored(Order $order): void
    {
        //
    }

    /**
     * Handle the Order "force deleted" event.
     */
    public function forceDeleted(Order $order): void
    {
        //
    }
}
