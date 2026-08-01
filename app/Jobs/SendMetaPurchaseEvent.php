<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\MetaConversionsService;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * Dispatched with dispatchAfterResponse() from order placement, so the
 * Graph API round trip happens after the customer already has their
 * order confirmation — never on the critical path.
 */
class SendMetaPurchaseEvent
{
    use Dispatchable;

    public function __construct(
        private readonly int $orderId,
        private readonly string $platform,
        private readonly bool $attEnabled,
    ) {
    }

    public function handle(): void
    {
        $order = Order::find($this->orderId);
        if ($order) {
            MetaConversionsService::sendPurchase($order, $this->platform, $this->attEnabled);
        }
    }
}
