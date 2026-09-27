<?php

namespace App\Observers;

use App\Models\Order;
use App\Models\OrderReference;
use App\Models\User;
use App\Services\XpService;
use App\Services\ChallengeService;
use App\Services\ScratchCardService;
use Illuminate\Support\Facades\Log;

class OrderObserver
{
    /**
     * Handle the Order "created" event.
     */
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
