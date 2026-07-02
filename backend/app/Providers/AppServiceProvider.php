<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One tenant context per request lifecycle. Everything tenant-aware
        // (global scopes, middleware, controllers, jobs) resolves this.
        $this->app->scoped(TenantContext::class, static fn (): TenantContext => new TenantContext());
    }

    public function boot(): void
    {
        $this->configureModels();
        $this->configureRateLimiting();
    }

    /**
     * Fail loudly in development on the two highest-value model mistakes:
     * lazy-loaded relations (silent N+1s) and silently discarded attributes
     * (typos in mass-assignment). We intentionally do NOT enable
     * preventAccessingMissingAttributes — it throws on legitimate access to
     * columns that weren't part of a partial select, which is more noise than
     * signal for this codebase.
     */
    private function configureModels(): void
    {
        $strict = ! $this->app->isProduction();

        Model::preventLazyLoading($strict);
        Model::preventSilentlyDiscardingAttributes($strict);
    }

    private function configureRateLimiting(): void
    {
        // General API limiter: keyed by authenticated user, else client IP.
        RateLimiter::for('api', static function (Request $request): Limit {
            $key = optional($request->user())->getAuthIdentifier() ?: $request->ip();

            return Limit::perMinute(120)->by((string) $key);
        });

        // Auth endpoints are brute-force targets — tighter, IP + email keyed.
        RateLimiter::for('auth', static function (Request $request): Limit {
            $email = (string) $request->input('email');

            return Limit::perMinute(10)->by(mb_strtolower($email).'|'.$request->ip());
        });

        // Telemetry ingest (device-facing) tolerates high burst per device.
        RateLimiter::for('ingest', static function (Request $request): Limit {
            $device = (string) ($request->header('X-Device-Id') ?: $request->ip());

            return Limit::perMinute(600)->by($device);
        });
    }
}
