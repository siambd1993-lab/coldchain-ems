<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * In production every HTTP request is a fresh PHP process, but feature
     * tests reuse one container: the JWT guard caches the resolved user, the
     * jwt-auth service caches the parsed token, and TenantContext keeps the
     * previous principal's scope. A second in-test request would silently run
     * as the first request's user (wrong actor for multi-user tests, and a
     * blacklisted token would never be re-checked). Reset all three so each
     * request authenticates from its own Authorization header, exactly like
     * production.
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $this->app['auth']->forgetGuards();
        // Two distinct jwt-auth singletons cache a parsed token: 'tymon.jwt'
        // (used by the guard) and 'tymon.jwt.auth' (the JWTAuth facade).
        $this->app['tymon.jwt']->unsetToken();
        \PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth::unsetToken();
        $this->app->make(\App\Support\TenantContext::class)->forget();

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }
}
