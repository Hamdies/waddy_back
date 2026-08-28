<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Delivers one FCM message out of band.
 *
 * Push delivery used to run inline inside the customer's HTTP request, and
 * every message cost two blocking round trips to Google: an OAuth token
 * exchange followed by the send itself. On a checkout that fires several
 * notifications, that is seconds of the customer's request spent waiting on
 * a third party that may be slow or down.
 */
class SendPushNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** FCM rejects a token that has been uninstalled; retrying cannot fix that. */
    public $tries = 3;

    public $backoff = [10, 60];

    /** Never let a hung request occupy a worker indefinitely. */
    public $timeout = 30;

    public function __construct(
        private array $payload,
        private array $credentials
    ) {
    }

    public function handle(): void
    {
        $projectId = data_get($this->credentials, 'project_id');

        if (!$projectId) {
            return;
        }

        $token = $this->accessToken();

        if (!$token) {
            Log::warning('FCM access token unavailable; push dropped');
            return;
        }

        $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ])
            ->timeout(15)
            ->post('https://fcm.googleapis.com/v1/projects/' . $projectId . '/messages:send', $this->payload);

        if ($response->failed()) {
            // 401 usually means the cached token went stale early — drop it so
            // the retry mints a fresh one instead of failing the same way.
            if ($response->status() === 401) {
                Cache::forget($this->tokenCacheKey());
            }

            Log::warning('FCM send failed', [
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 500),
            ]);

            $response->throw();
        }
    }

    /**
     * Google's OAuth tokens last an hour, but the old code minted a new one for
     * every single message. Cached just under the hour, with a little headroom.
     */
    private function accessToken(): ?string
    {
        return Cache::remember($this->tokenCacheKey(), 3300, function () {
            $jwtHeader = base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $jwtPayload = base64_encode(json_encode([
                'iss' => $this->credentials['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'exp' => time() + 3600,
                'iat' => time(),
            ]));

            $unsignedJwt = $jwtHeader . '.' . $jwtPayload;
            openssl_sign($unsignedJwt, $signature, $this->credentials['private_key'], OPENSSL_ALGO_SHA256);
            $jwt = $unsignedJwt . '.' . base64_encode($signature);

            $response = Http::asForm()->timeout(15)->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            return $response->json('access_token');
        });
    }

    private function tokenCacheKey(): string
    {
        return 'fcm_access_token_' . md5((string) data_get($this->credentials, 'client_email'));
    }

    public function failed(\Throwable $e): void
    {
        Log::error('Push notification permanently failed: ' . $e->getMessage());
    }
}
