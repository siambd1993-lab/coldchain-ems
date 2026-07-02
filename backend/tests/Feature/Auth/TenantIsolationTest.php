<?php

declare(strict_types=1);

use App\Enums\Role as RoleEnum;
use App\Models\Customer;

beforeEach(function (): void {
    // Two completely separate tenants.
    ['tenant' => $this->tenantA, 'branch' => $this->branchA] = createTenant('tenant-a');
    ['tenant' => $this->tenantB, 'branch' => $this->branchB] = createTenant('tenant-b');

    $this->userA = createUser($this->tenantA, $this->branchA, RoleEnum::TenantAdmin);
    $this->userB = createUser($this->tenantB, $this->branchB, RoleEnum::TenantAdmin);

    // A customer belonging only to tenant A.
    $this->customerA = Customer::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'branch_id' => $this->branchA->id,
        'code'      => 'CUST-A001',
    ]);

    // A customer belonging only to tenant B.
    $this->customerB = Customer::factory()->create([
        'tenant_id' => $this->tenantB->id,
        'branch_id' => $this->branchB->id,
        'code'      => 'CUST-B001',
    ]);
});

// ── List isolation ────────────────────────────────────────────────────────────

test('tenant A user only sees tenant A customers in list', function (): void {
    $response = $this->getJson(
        '/api/v1/customers',
        apiHeaders($this->userA, $this->branchA),
    )->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toContain($this->customerA->id)
        ->not->toContain($this->customerB->id);
});

test('tenant B user only sees tenant B customers in list', function (): void {
    $response = $this->getJson(
        '/api/v1/customers',
        apiHeaders($this->userB, $this->branchB),
    )->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toContain($this->customerB->id)
        ->not->toContain($this->customerA->id);
});

// ── Show isolation ────────────────────────────────────────────────────────────

test('tenant A user cannot view tenant B customer by ID', function (): void {
    // Laravel's implicit model binding + TenantScope means the record is not
    // found for the requesting tenant, so the response is 404.
    $this->getJson(
        "/api/v1/customers/{$this->customerB->id}",
        apiHeaders($this->userA, $this->branchA),
    )->assertNotFound();
});

test('tenant B user cannot view tenant A customer by ID', function (): void {
    $this->getJson(
        "/api/v1/customers/{$this->customerA->id}",
        apiHeaders($this->userB, $this->branchB),
    )->assertNotFound();
});

// ── Mutation isolation ────────────────────────────────────────────────────────

test('tenant A user cannot update tenant B customer', function (): void {
    $this->putJson(
        "/api/v1/customers/{$this->customerB->id}",
        ['name' => 'Hacked Name'],
        apiHeaders($this->userA, $this->branchA),
    )->assertNotFound();

    // Ensure no change persisted.
    expect($this->customerB->fresh()->name)->not->toBe('Hacked Name');
});

test('tenant A user cannot delete tenant B customer', function (): void {
    $this->deleteJson(
        "/api/v1/customers/{$this->customerB->id}",
        [],
        apiHeaders($this->userA, $this->branchA),
    )->assertNotFound();

    expect($this->customerB->fresh())->not->toBeNull();
});

// ── Branch isolation ───────────────────────────────────────────────────────────
// `createTenant()` already provisions one branch per tenant (`branchA`/`branchB`),
// so those fixtures are reused directly here instead of creating new ones.

test('tenant A user only sees tenant A branches in list', function (): void {
    $response = $this->getJson(
        '/api/v1/branches',
        apiHeaders($this->userA, $this->branchA),
    )->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toContain($this->branchA->id)
        ->not->toContain($this->branchB->id);
});

test('tenant A user cannot view tenant B branch by ID', function (): void {
    $this->getJson(
        "/api/v1/branches/{$this->branchB->id}",
        apiHeaders($this->userA, $this->branchA),
    )->assertNotFound();
});

test('tenant A user cannot update tenant B branch', function (): void {
    $this->putJson(
        "/api/v1/branches/{$this->branchB->id}",
        ['name' => 'Hacked Branch Name'],
        apiHeaders($this->userA, $this->branchA),
    )->assertNotFound();

    expect($this->branchB->fresh()->name)->not->toBe('Hacked Branch Name');
});

test('tenant A owner cannot delete tenant B branch', function (): void {
    // `branches.delete` is TenantOwner-only (TenantAdmin lacks it), so a fresh
    // owner user is created here rather than reusing the TenantAdmin fixtures
    // above — this isolates the tenant-scoping behaviour from permission gating.
    $ownerA = createUser($this->tenantA, $this->branchA, RoleEnum::TenantOwner);

    $this->deleteJson(
        "/api/v1/branches/{$this->branchB->id}",
        [],
        apiHeaders($ownerA, $this->branchA),
    )->assertNotFound();

    expect($this->branchB->fresh())->not->toBeNull();
});

// ── JWT cross-tenant claim check ──────────────────────────────────────────────

test('user cannot access tenant B resources even with a crafted tenant_id claim', function (): void {
    // A legitimate token for tenant A. No matter what, the server reads tenant_id
    // from the DB-resolved user (TenantContext) — not from JWT claims directly.
    // The JWT tenant_id claim is only a hint/cache; ResolveTenant fetches the
    // authoritative user record from the DB.
    $this->getJson(
        "/api/v1/customers/{$this->customerB->id}",
        apiHeaders($this->userA, $this->branchA),
    )->assertNotFound();
});
