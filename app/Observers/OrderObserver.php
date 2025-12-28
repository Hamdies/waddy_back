<?php

namespace App\Observers;

use App\Models\Order;
use App\Models\OrderReference;
use App\Models\User;
use App\Services\XpService;
use App\Services\ChallengeService;
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
    }

    /**
     * Handle the Order "updated" event.
     * Award XP when order status changes to 'delivered'.
     */
    public function updated(Order $order): void
    {
        // Check if order status just changed to 'delivered'
        if ($order->isDirty('order_status') && $order->order_status === 'delivered') {
            $this->handleOrderDelivered($order);
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

            // Check and update challenge progress
            ChallengeService::checkProgress($user, $order);

            Log::info("XP processed successfully for order: {$order->id}");
        } catch (\Exception $e) {
            Log::error("Failed to process XP for order {$order->id}: " . $e->getMessage());
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
