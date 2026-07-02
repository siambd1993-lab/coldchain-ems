<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Marks a model as tenant-owned.
 *
 * Applies the {@see TenantScope} global scope for automatic row-level isolation
 * and auto-stamps `tenant_id` from {@see TenantContext} on insert, so callers
 * never have to remember to set it (and cannot accidentally set someone else's).
 *
 * Models with a differently-named key can override {@see getTenantKeyName()}.
 */
trait BelongsToTenant
{
    /** When true (temporarily), the global tenant scope is skipped. */
    protected static bool $tenancyDisabled = false;

    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope());

        static::creating(function ($model): void {
            if (static::$tenancyDisabled) {
                return;
            }

            $tenantId = app(TenantContext::class)->tenantId();

            // When a tenant is in scope (the HTTP request path), it is
            // authoritative: force it onto the row so a crafted `tenant_id` in
            // a mass-assignment payload cannot plant data in another tenant.
            // Trusted/system contexts (no tenant resolved) keep whatever the
            // caller set explicitly.
            if ($tenantId !== null) {
                $model->setAttribute($model->getTenantKeyName(), $tenantId);
            }
        });
    }

    /** Column that carries the tenant foreign key. */
    public function getTenantKeyName(): string
    {
        return 'tenant_id';
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, $this->getTenantKeyName());
    }

    /**
     * Run a callback with tenant scoping disabled. Intended for trusted
     * system/console code that must span tenants (reporting, migrations,
     * platform tooling). Always pair reads with an explicit tenant filter.
     *
     * @template TReturn
     * @param  callable():TReturn  $callback
     * @return TReturn
     */
    public static function withoutTenancy(callable $callback): mixed
    {
        $previous = static::$tenancyDisabled;
        static::$tenancyDisabled = true;

        try {
            return $callback();
        } finally {
            static::$tenancyDisabled = $previous;
        }
    }

    /** Query builder with the tenant global scope removed. */
    public static function withoutTenantScope(): \Illuminate\Database\Eloquent\Builder
    {
        return static::query()->withoutGlobalScope(TenantScope::class);
    }
}
