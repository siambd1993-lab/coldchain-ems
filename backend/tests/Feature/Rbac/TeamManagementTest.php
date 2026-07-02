<?php

declare(strict_types=1);

use App\Enums\Role as RoleEnum;
use App\Models\Role;
use App\Models\User;

beforeEach(function (): void {
    ['tenant' => $this->tenant, 'branch' => $this->branch] = createTenant();
    // TenantAdmin covers user CRUD except delete; role management and user
    // deletion belong to TenantOwner per the RBAC matrix.
    $this->admin        = createUser($this->tenant, $this->branch, RoleEnum::TenantAdmin);
    $this->owner        = createUser($this->tenant, $this->branch, RoleEnum::TenantOwner);
    $this->headers      = apiHeaders($this->admin, $this->branch);
    $this->ownerHeaders = apiHeaders($this->owner, $this->branch);
});

// ── Users ─────────────────────────────────────────────────────────────────────

test('admin can list only own-tenant users', function (): void {
    ['tenant' => $tenantB, 'branch' => $branchB] = createTenant('other-tenant');
    createUser($tenantB, $branchB, RoleEnum::Operator);

    $resp = $this->getJson('/api/v1/users', $this->headers)->assertOk();

    $emails = collect($resp->json('data'))->pluck('email');
    expect($emails)->toContain($this->admin->email);
    expect($resp->json('meta.total'))->toBe(2);
});

test('operator cannot list users', function (): void {
    $operator = createUser($this->tenant, $this->branch, RoleEnum::Operator);

    $this->getJson('/api/v1/users', apiHeaders($operator, $this->branch))
        ->assertForbidden();
});

test('admin can create a user with a role and that user can log in', function (): void {
    $roleId = Role::where('tenant_id', $this->tenant->id)
        ->where('slug', RoleEnum::Operator->value)->value('id');

    $this->postJson('/api/v1/users', [
        'name'     => 'New Operator',
        'email'    => 'new.op@arcturus.test',
        'password' => 'secret-pass-123',
        'role_ids' => [$roleId],
    ], $this->headers)
        ->assertCreated()
        ->assertJsonPath('data.email', 'new.op@arcturus.test')
        ->assertJsonPath('data.roles.0', RoleEnum::Operator->value);

    $this->postJson('/api/v1/auth/login', [
        'email'    => 'new.op@arcturus.test',
        'password' => 'secret-pass-123',
    ])->assertOk();
});

test('duplicate email is rejected', function (): void {
    $this->postJson('/api/v1/users', [
        'name'     => 'Dup',
        'email'    => $this->admin->email,
        'password' => 'secret-pass-123',
    ], $this->headers)->assertUnprocessable();
});

test('a role from another tenant cannot be assigned', function (): void {
    ['tenant' => $tenantB] = createTenant('other-tenant');
    $foreignRole = Role::where('tenant_id', $tenantB->id)
        ->where('slug', RoleEnum::TenantOwner->value)->value('id');

    $this->postJson('/api/v1/users', [
        'name'     => 'Sneaky',
        'email'    => 'sneaky@arcturus.test',
        'password' => 'secret-pass-123',
        'role_ids' => [$foreignRole],
    ], $this->headers)->assertUnprocessable();
});

test('suspending a user revokes refresh tokens and blocks login', function (): void {
    $user = createUser($this->tenant, $this->branch, RoleEnum::Operator);

    // Give the user a live session (refresh token family).
    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email, 'password' => 'password',
    ])->assertOk();

    $this->putJson("/api/v1/users/{$user->id}", ['status' => 'suspended'], $this->headers)
        ->assertOk()
        ->assertJsonPath('data.status', 'suspended');

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email, 'password' => 'password',
    ])->assertUnauthorized();

    expect($user->refreshTokens()->whereNull('revoked_at')->count())->toBe(0);
});

test('user cannot delete their own account', function (): void {
    $this->deleteJson("/api/v1/users/{$this->owner->id}", [], $this->ownerHeaders)
        ->assertConflict();
});

test('admin without users.delete cannot delete a user', function (): void {
    $user = createUser($this->tenant, $this->branch, RoleEnum::Operator);

    $this->deleteJson("/api/v1/users/{$user->id}", [], $this->headers)
        ->assertForbidden();
});

test('admin can delete another user', function (): void {
    $user = createUser($this->tenant, $this->branch, RoleEnum::Operator);

    $this->deleteJson("/api/v1/users/{$user->id}", [], $this->ownerHeaders)->assertOk();
    expect(User::find($user->id))->toBeNull(); // soft-deleted
});

test('roles can be re-synced on a user', function (): void {
    $user = createUser($this->tenant, $this->branch, RoleEnum::Operator);

    $accountant = Role::where('tenant_id', $this->tenant->id)
        ->where('slug', RoleEnum::Accountant->value)->value('id');

    $this->putJson("/api/v1/users/{$user->id}/roles", [
        'role_ids' => [$accountant],
    ], $this->headers)
        ->assertOk()
        ->assertJsonPath('data.roles.0', RoleEnum::Accountant->value);
});

// ── Roles ─────────────────────────────────────────────────────────────────────

test('roles index lists system roles with user counts', function (): void {
    $resp = $this->getJson('/api/v1/roles', $this->headers)->assertOk();

    $slugs = collect($resp->json('data'))->pluck('slug');
    expect($slugs)->toContain(RoleEnum::TenantAdmin->value)
        ->and($slugs)->not->toContain(RoleEnum::PlatformAdmin->value);
});

test('admin can create a custom role and assign it', function (): void {
    $resp = $this->postJson('/api/v1/roles', [
        'name'        => 'Gate Clerk',
        'description' => 'Weighbridge intake only.',
        'permissions' => ['stock.view', 'stock.stock_in', 'customers.view'],
    ], $this->ownerHeaders)
        ->assertCreated()
        ->assertJsonPath('data.slug', 'gate_clerk')
        ->assertJsonPath('data.is_system', false);

    $user = createUser($this->tenant, $this->branch, RoleEnum::Operator);
    $this->putJson("/api/v1/users/{$user->id}/roles", [
        'role_ids' => [$resp->json('data.id')],
    ], $this->headers)->assertOk();

    // The custom role's permissions now gate real requests.
    $this->getJson('/api/v1/stock-lots', apiHeaders($user->fresh(), $this->branch))->assertOk();
    $this->getJson('/api/v1/invoices', apiHeaders($user->fresh(), $this->branch))->assertForbidden();
});

test('unknown permission slugs are rejected', function (): void {
    $this->postJson('/api/v1/roles', [
        'name'        => 'Broken',
        'permissions' => ['stock.view', 'not.a_permission'],
    ], $this->ownerHeaders)->assertUnprocessable();
});

test('system roles cannot be modified or deleted', function (): void {
    $system = Role::where('tenant_id', $this->tenant->id)
        ->where('slug', RoleEnum::Operator->value)->firstOrFail();

    $this->putJson("/api/v1/roles/{$system->id}", ['name' => 'Hacked'], $this->ownerHeaders)
        ->assertConflict();
    $this->deleteJson("/api/v1/roles/{$system->id}", [], $this->ownerHeaders)
        ->assertConflict();
});

test('a role that is in use cannot be deleted', function (): void {
    $role = Role::create([
        'tenant_id' => $this->tenant->id, 'name' => 'Temp', 'slug' => 'temp',
        'permissions' => ['stock.view'], 'is_system' => false,
    ]);
    $user = createUser($this->tenant, $this->branch, RoleEnum::Operator);
    $user->roles()->attach($role->id);

    $this->deleteJson("/api/v1/roles/{$role->id}", [], $this->ownerHeaders)->assertConflict();

    $user->roles()->detach($role->id);
    $this->deleteJson("/api/v1/roles/{$role->id}", [], $this->ownerHeaders)->assertOk();
});

test('another tenants role is not reachable', function (): void {
    ['tenant' => $tenantB] = createTenant('other-tenant');
    $foreign = Role::where('tenant_id', $tenantB->id)
        ->where('slug', RoleEnum::Operator->value)->firstOrFail();

    $this->putJson("/api/v1/roles/{$foreign->id}", ['name' => 'X'], $this->ownerHeaders)
        ->assertNotFound();
});

test('permission catalog is grouped by module', function (): void {
    $resp = $this->getJson('/api/v1/permissions', $this->headers)->assertOk();

    $modules = collect($resp->json('data'))->pluck('module');
    expect($modules)->toContain('stock')->and($modules)->toContain('billing');

    $stock = collect($resp->json('data'))->firstWhere('module', 'stock');
    expect(collect($stock['permissions'])->pluck('value'))->toContain('stock.adjust');
});
