<?php

namespace Modules\PlacesToVisit\Services;

use Illuminate\Support\Facades\DB;
use Modules\PlacesToVisit\Entities\Place;
use Modules\PlacesToVisit\Entities\PlacePrize;

/**
 * Counter-side redemption. The venue's page holds a per-venue secret in its
 * URL, so a code can only be burned at the venue that owes the item — that
 * plus the single-use flip is what closes the "screenshot it to a friend"
 * hole without asking the cafe to install anything.
 */
class PrizeRedemptionService
{
    /**
     * Burn a voucher at a venue.
     *
     * @return array{success:bool, code:string, message:string, prize?:PlacePrize}
     */
    public function redeem(string $code, Place $venue, ?string $ip = null): array
    {
        $code = $this->normalize($code);

        if ($code === '') {
            return $this->fail('not_found');
        }

        return DB::transaction(function () use ($code, $venue, $ip) {
            $prize = PlacePrize::where('code', $code)->lockForUpdate()->first();

            if (!$prize) {
                return $this->fail('not_found');
            }

            if ((int) $prize->place_id !== (int) $venue->id) {
                return $this->fail('wrong_venue');
            }

            if ($prize->status === PlacePrize::STATUS_REDEEMED) {
                return $this->fail('already_redeemed', [
                    'prize' => $prize,
                    'redeemed_at' => $prize->redeemed_at?->toDateTimeString(),
                ]);
            }

            if ($prize->isExpired()) {
                // Reconcile lazily in case the hourly cron hasn't run yet
                if ($prize->status === PlacePrize::STATUS_ACTIVE) {
                    $prize->update(['status' => PlacePrize::STATUS_EXPIRED]);
                }
                return $this->fail('expired', ['prize' => $prize]);
            }

            $prize->update([
                'status' => PlacePrize::STATUS_REDEEMED,
                'redeemed_at' => now(),
                'redeemed_ip' => $ip,
            ]);

            return [
                'success' => true,
                'code' => 'ok',
                'message' => translate('messages.prize_redeem_ok'),
                'prize' => $prize->fresh('place'),
            ];
        });
    }

    /**
     * Cashiers type these off a phone screen — accept lowercase, missing
     * dashes, stray spaces, and the pasted-with-whitespace case.
     */
    public function normalize(string $code): string
    {
        $clean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '');

        if (strlen($clean) === 8) {
            return substr($clean, 0, 4) . '-' . substr($clean, 4);
        }

        return $clean;
    }

    protected function fail(string $code, array $extra = []): array
    {
        return array_merge([
            'success' => false,
            'code' => $code,
            'message' => translate("messages.prize_redeem_{$code}"),
        ], $extra);
    }
}
