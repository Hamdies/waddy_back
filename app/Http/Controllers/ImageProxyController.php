<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Fetches a remote image on the client's behalf, for cases where a browser
 * cannot load it directly (CORS, mixed content).
 *
 * This endpoint is unauthenticated and takes a URL from the caller, which makes
 * it a server-side request forgery primitive unless it is constrained. It
 * previously passed the parameter straight to an HTTP client, so anyone could
 * make the server fetch cloud metadata endpoints, services bound to localhost,
 * or hosts inside the private network, and read the response body back.
 *
 * The constraints below are all load-bearing:
 *   - only http/https, so file://, gopher:// and friends are out
 *   - every resolved IP is checked against private and reserved ranges
 *   - redirects are NOT followed, since a permitted host can redirect to an
 *     internal one and bypass the check above
 *   - only image responses are returned, so this cannot be used to read text
 *     from an internal service that happens to be publicly routable
 *   - responses are size-capped and time-limited
 *
 * Residual risk: a hostname could resolve to a public IP during validation and
 * a private one when fetched (DNS rebinding). Closing that requires pinning the
 * connection to the validated IP. Given this only ever returns image bytes, the
 * remaining exposure is small, but it is the reason to prefer an allowlist —
 * set IMAGE_PROXY_ALLOWED_HOSTS and the rebinding window closes too.
 */
class ImageProxyController extends Controller
{
    private const MAX_BYTES = 8 * 1024 * 1024;
    private const TIMEOUT_SECONDS = 8;

    public function __invoke(Request $request)
    {
        $url = (string) $request->query('url', '');

        if ($url === '') {
            return response()->json(['message' => 'The url parameter is required.'], 400);
        }

        if (!$this->isFetchable($url, $reason)) {
            Log::warning('Image proxy rejected a URL', [
                'reason' => $reason,
                'url' => mb_substr($url, 0, 200),
                'ip' => $request->ip(),
            ]);

            return response()->json(['message' => 'That URL cannot be fetched.'], 422);
        }

        try {
            $response = Http::withHeaders(['User-Agent' => 'Waddy-Image-Proxy'])
                ->withOptions(['allow_redirects' => false])
                ->timeout(self::TIMEOUT_SECONDS)
                ->get($url);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Could not fetch that image.'], 502);
        }

        if (!$response->successful()) {
            return response()->json(['message' => 'Could not fetch that image.'], 502);
        }

        $contentType = (string) $response->header('Content-Type');

        if (!str_starts_with(strtolower($contentType), 'image/')) {
            return response()->json(['message' => 'That URL is not an image.'], 422);
        }

        $body = $response->body();

        if (strlen($body) > self::MAX_BYTES) {
            return response()->json(['message' => 'That image is too large.'], 413);
        }

        return response($body, 200)
            ->header('Content-Type', $contentType)
            ->header('Cache-Control', 'public, max-age=86400')
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('Access-Control-Allow-Origin', '*');
    }

    private function isFetchable(string $url, ?string &$reason = null): bool
    {
        $parts = parse_url($url);

        if ($parts === false || empty($parts['host'])) {
            $reason = 'unparseable';
            return false;
        }

        if (!in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) {
            $reason = 'scheme';
            return false;
        }

        $host = $parts['host'];

        // An allowlist, when configured, is the strongest form of this check.
        $allowed = array_filter(config('imageproxy.allowed_hosts', []));

        if ($allowed !== []) {
            foreach ($allowed as $candidate) {
                if (strcasecmp($host, $candidate) === 0 || str_ends_with(strtolower($host), '.' . strtolower($candidate))) {
                    return true;
                }
            }

            $reason = 'host_not_allowed';
            return false;
        }

        foreach ($this->resolve($host) as $ip) {
            if (!$this->isPublicIp($ip)) {
                $reason = 'private_ip:' . $ip;
                return false;
            }
        }

        return true;
    }

    /** @return string[] */
    private function resolve(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $ips = array_merge(
            gethostbynamel($host) ?: [],
            array_column(@dns_get_record($host, DNS_AAAA) ?: [], 'ipv6')
        );

        // No resolution means nothing to fetch; treat as unroutable.
        return $ips ?: ['0.0.0.0'];
    }

    private function isPublicIp(string $ip): bool
    {
        return (bool) filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }
}
