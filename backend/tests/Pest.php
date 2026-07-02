<?php

declare(strict_types=1);

use App\Enums\Role as RoleEnum;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

pest()->extend(Tests\TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(Tests\TestCase::class)
    ->in('Unit');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
|
| These helpers are available in every test file via `uses()`.
|
*/

/**
 * Create a tenant with seeded system roles and a primary branch.
 *
 * @return array{tenant: Tenant, branch: Branch}
 */
function createTenant(string $slug = 'test-tenant'): array
{
    $tenant = Tenant::factory()->create(['slug' => $slug]);
    (new RoleSeeder())->runForTenant($tenant);
    $branch = Branch::factory()->for($tenant)->create(['code' => 'BR-001']);

    return compact('tenant', 'branch');
}

/**
 * Create a user attached to the given tenant and with the given system role.
 * Branch restriction is applied when $restrictToBranch is true.
 */
function createUser(
    Tenant $tenant,
    ?Branch $branch,
    RoleEnum $role,
    bool $restrictToBranch = false,
): User {
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'branch_id' => $branch?->id,
        'status'    => 'active',
    ]);

    $roleModel = Role::where('tenant_id', $tenant->id)->where('slug', $role->value)->firstOrFail();
    $user->roles()->attach($roleModel->id);

    if ($restrictToBranch && $branch !== null) {
        $user->branches()->attach($branch->id);
    }

    return $user;
}

/**
 * Return a valid JWT token string for the given user.
 */
function tokenFor(User $user): string
{
    // Load relations required by getJWTCustomClaims / UserResource.
    $user->loadMissing(['roles', 'branches']);

    return \PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth::fromUser($user);
}

/**
 * Convenience: act as a user with bearer token and an active-branch header.
 */
function actingAsUser(User $user, ?Branch $branch = null): Illuminate\Testing\PendingCommand|Illuminate\Contracts\Auth\Guard
{
    // This is intentionally not the right return type — callers use it differently.
    // Individual test helpers call `withToken` / `withHeaders` on the response.
    return auth()->setUser($user);
}

/**
 * Build headers for an authenticated API call.
 *
 * @return array<string,string>
 */
function apiHeaders(User $user, ?Branch $branch = null): array
{
    $token   = tokenFor($user);
    $headers = ['Authorization' => "Bearer {$token}"];

    if ($branch !== null) {
        $headers['X-Branch-Id'] = (string) $branch->id;
    }

    return $headers;
}
