<?php

namespace App\Services;

use App\CentralLogics\Helpers;
use App\Models\ZoneRequest;
use Illuminate\Support\Facades\Log;

/**
 * Delivers on the "we'll tell you the moment we launch here" promise.
 *
 * Without this, notify-me is a broken promise — worse for trust than never
 * having offered it. This is the trigger that makes the copy honest.
 */
class ZoneLaunchNotifier
{
    /**
     * Notify everyone who asked for delivery in a zone that just went live.
     *
     * Only rows with a usable push token and not already notified are touched,
     * so re-activating a zone (or a double-click on the admin toggle) can't
     * spam the same person twice.
     *
     * Never throws: a push failure must not roll back or block the zone
     * activation itself, which is the far more important operation.
     *
     * @return int number of requesters notified
     */
    public static function notifyForZone(int $zoneId): int
    {
        $sent = 0;

        try {
            ZoneRequest::where('nearest_zone_id', $zoneId)
                ->where('notified', 0)
                ->where('has_push', 1)
                ->whereNotNull('fcm_token')
                ->chunkById(200, function ($requests) use (&$sent) {
                    foreach ($requests as $request) {
                        try {
                            Helpers::send_push_notif_to_device($request->fcm_token, [
                                'title' => translate('messages.we_have_launched_in_your_area'),
                                'description' => translate('messages.we_have_launched_in_your_area_body'),
                                'order_id' => '',
                                'image' => '',
                                'type' => 'zone_launch',
                            ]);
                            $request->notified = 1;
                            $request->save();
                            $sent++;
                        } catch (\Throwable $e) {
                            // One bad token must not stop the rest of the batch.
                            Log::warning('Zone launch push failed', [
                                'zone_request_id' => $request->id,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                });
        } catch (\Throwable $e) {
            Log::error('Zone launch notification sweep failed', [
                'zone_id' => $zoneId,
                'error' => $e->getMessage(),
            ]);
        }

        return $sent;
    }
}
