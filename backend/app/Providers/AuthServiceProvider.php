<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\Permission;
use App\Support\TenantContext;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Model → Policy map. Populated as domain models land (task 7+).
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [];

    public function boot(): void
    {
        $this->registerPolicies();

        // Platform admins bypass every gate. Returning a non-null value from a
        // `before` callback short-circuits all subsequent checks.
        Gate::before(function (?Authorizable $user, string $ability): ?bool {
            return app(TenantContext::class)->isPlatformAdmin() ? true : null;
        });

        // Expose every permission as a Gate ability so controllers and policies
        // can use `$user->can('customers.create')` / `Gate::authorize(...)`
        // with the exact same source of truth as the `permission:` middleware.
        foreach (Permission::cases() as $permission) {
            Gate::define(
                $permission->value,
                static fn (?Authorizable $user = null): bool => app(TenantContext::class)->can($permission),
            );
        }
    }
}
