<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * RT-3: "an order of this store changed" on `private-store.{id}`. Carries no
 * order data, only what the app needs to decide to reload: the store app
 * fetches its running list when it hears this. Sent by StoreRealtime after
 * the response, so it never slows the request that changed the order.
 */
class StoreOrderChanged implements ShouldBroadcastNow
{
    public function __construct(
        public int $storeId,
        public int $orderId,
        public ?string $orderStatus,
        public ?string $updatedAt,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('store.' . $this->storeId);
    }

    public function broadcastAs(): string
    {
        return 'order.changed';
    }

    public function broadcastWith(): array
    {
        return ['order_id' => $this->orderId, 'order_status' => $this->orderStatus, 'updated_at' => $this->updatedAt];
    }
}
