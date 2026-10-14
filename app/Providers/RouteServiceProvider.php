<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * This namespace is applied to your controller routes.
     *
     * In addition, it is set as the URL generator's root namespace.
     *
     * @var string
     */
    protected $namespace = 'App\Http\Controllers';


    /**
     * The path to the "home" route for your application.
     *
     * This is used by Laravel authentication to redirect users after login.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * The controller namespace for the application.
     *
     * When present, controller route declarations will automatically be prefixed with this namespace.
     *
     * @var string|null
     */
    // protected $namespace = 'App\\Http\\Controllers';

    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot()
    {
        $this->configureRateLimiting();

        $this->routes(function () {

            Route::middleware('web')
                ->namespace($this->namespace)
                ->group(base_path('routes/web.php'));

            Route::prefix('admin')
                ->middleware('web')
                ->namespace($this->namespace)
                ->group(base_path('routes/admin.php'));

            Route::prefix('vendor-panel')
                ->middleware('web')
                ->namespace($this->namespace)
                ->group(base_path('routes/vendor.php'));

            Route::prefix('api/v1')
                ->middleware('api')
                ->namespace($this->namespace)
                ->group(base_path('routes/api/v1/api.php'));

            Route::prefix('api/v2')
                ->middleware('api')
                ->namespace($this->namespace)
                ->group(base_path('routes/api/v2/api.php'));


            //new routes
            Route::prefix('admin')
                ->middleware('web')
                ->namespace($this->namespace)
                ->group(base_path('routes/admin/routes.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting()
    {
        RateLimiter::for('api', function (Request $request) {
            // Capacity testing only — see config/loadtest.php. Empty in normal
            // operation, so this is a no-op unless LOADTEST_EXEMPT_IPS is set.
            if (in_array($request->ip(), config('loadtest.exempt_ips', []), true)) {
                return Limit::none();
            }

            return Limit::perMinute(120)->by(optional($request->user())->id ?: $request->ip());
        });

        // Egyptian carriers run heavy CGNAT (see routes/api/v1/api.php), so a tight
        // per-IP cap rejects real people who merely share a public IP. The tight
        // limit sits on the account being attacked instead; the IP limit is only a
        // loose backstop against one machine spraying many numbers.
        RateLimiter::for('auth', function (Request $request) {
            return self::identity_limits($request, 10, 60);
        });

        RateLimiter::for('otp', function (Request $request) {
            return self::identity_limits($request, 5, 30);
        });
    }

    /**
     * @param  int  $per_identity  per minute, per phone/email in the request
     * @param  int  $per_ip        per minute, per client IP
     */
    protected static function identity_limits(Request $request, int $per_identity, int $per_ip): array
    {
        $respond = fn () => response()->json([
            'errors' => [['code' => 'too_many_requests', 'message' => translate('messages.too_many_requests_try_again_shortly')]],
        ], 429);

        $limits = [Limit::perMinute($per_ip)->by('ip:' . $request->ip())->response($respond)];

        $identity = $request->input('phone') ?? $request->input('email_or_phone') ?? $request->input('email');
        // No identity (guest/request runs on every first launch) means only the
        // loose IP limit applies; a tight one here would hit CGNAT users again.
        if (is_string($identity) && $identity !== '') {
            // Strip formatting so "+20 100…" and "+20100…" share one bucket.
            $key = strtolower(preg_replace('/[^0-9a-zA-Z@.+_-]/', '', $identity));
            $limits[] = Limit::perMinute($per_identity)->by('id:' . $key)->response($respond);
        }

        return $limits;
    }
}
