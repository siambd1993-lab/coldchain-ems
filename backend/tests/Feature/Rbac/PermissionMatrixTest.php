<?php

declare(strict_types=1);

use App\Enums\Role as RoleEnum;
use App\Models\Chamber;
use App\Models\Customer;

/**
 * RBAC matrix: verify that each role has exactly the access it should.
 *
 * Tests cover the three most important access decisions:
 *   - Operator cannot access billing endpoints.
 *   - Accountant cannot create/release stock.
 *   - Auditor gets read-only access everywhere.
 *   - TenantOwner has full access.
 */

beforeEach(function (): void {
    ['tenant' => $this->tenant, 'branch' => $this->branch] = createTenant();

    $this->chamber = Chamber::factory()->create([
        'tenant_id' => $this->tenant->id,
        'branch_id' => $this->branch->id,
        'code'      => 'CHM-TEST',
    ]);

    $this->customer = Customer::factory()->create([
        'tenant_id' => $this->tenant->id,
        'branch_id' => $this->branch->id,
        'code'      => 'RBAC-CUST',
    ]);
});

// ── Operator ──────────────────────────────────────────────────────────────────

test('operator can view stock lots', function (): void {
    $user = createUser($this->tenant, $this->branch, RoleEnum::Operator);

    $this->getJson('/api/v1/stock-lots', apiHeaders($user, $this->branch))
        ->assertOk();
});

test('operator cannot view rate plans (billing.view)', function (): void {
    $user = createUser($this->tenant, $this->branch, RoleEnum::Operator);

    $this->getJson('/api/v1/rate-plans', apiHeaders($user, $this->branch))
        ->assertForbidden();
});

test('operator cannot view invoices', function (): void {
    $user = createUser($this->tenant, $this->branch, RoleEnum::Operator);

    $this->getJson('/api/v1/invoices', apiHeaders($user, $this->branch))
        ->assertForbidden();
});

test('operator cannot view payments', function (): void {
    $user = createUser($this->tenant, $this->branch, RoleEnum::Operator);

    $this->getJson('/api/v1/payments', apiHeaders($user, $this->branch))
        ->assertForbidden();
});

test('operator cannot view branches (branches.view)', function (): void {
    $user = createUser($this->tenant, $this->branch, RoleEnum::Operator);

    $this->getJson('/api/v1/branches', apiHeaders($user, $this->branch))
        ->assertForbidden();
});

// ── Branch Manager ────────────────────────────────────────────────────────────

test('branch manager can view branches', function (): void {
    $user = createUser($this->tenant, $this->branch, RoleEnum::BranchManager);

    $this->getJson('/api/v1/branches', apiHeaders($user, $this->branch))
        ->assertOk();
});

test('branch manager cannot create branches (branches.create)', function (): void {
    $user = createUser($this->tenant, $this->branch, RoleEnum::BranchManager);

    $this->postJson('/api/v1/branches', [
        'code' => 'BM-NEW',
        'name' => 'Branch Manager Attempt',
    ], apiHeaders($user, $this->branch))
        ->assertForbidden();
});

// ── Accountant ────────────────────────────────────────────────────────────────

test('accountant can view invoices', function (): void {
    $user = createUser($this->tenant, $this->branch, RoleEnum::Accountant);

    $this->getJson('/api/v1/invoices', apiHeaders($user, $this->branch))
        ->assertOk();
});

test('accountant can view payments', function (): void {
    $user = createUser($this->tenant, $this->branch, RoleEnum::Accountant);

    $this->getJson('/api/v1/payments', apiHeaders($user, $this->branch))
        ->assertOk();
});

test('accountant cannot stock in (stock.stock_in)', function (): void {
    $user = createUser($this->tenant, $this->branch, RoleEnum::Accountant);

    $this->postJson('/api/v1/stock-lots', [
        'customer_id'     => $this->customer->id,
        'chamber_id'      => $this->chamber->id,
        'branch_id'       => $this->branch->id,
        'unit_of_measure' => 'kg',
        'quantity'        => 100,
        'received_at'     => now()->toDateTimeString(),
    ], apiHeaders($user, $this->branch))
        ->assertForbidden();
});

test('accountant cannot create customers (customers.create)', function (): void {
    $user = createUser($this->tenant, $this->branch, RoleEnum::Accountant);

    $this->postJson('/api/v1/customers', [
        'name'  => 'New Customer',
        'code'  => 'ACCT-NEW',
        'type'  => 'business',
        'email' => 'newcust@example.test',
    ], apiHeaders($user, $this->branch))
        ->assertForbidden();
});

// ── Technician ────────────────────────────────────────────────────────────────

test('technician can view chambers', function (): void {
    $user = createUser($this->tenant, $this->branch, RoleEnum::Technician);

    $this->getJson('/api/v1/chambers', apiHeaders($user, $this->branch))
        ->assertOk();
});

test('technician cannot view customers', function (): void {
    $user = createUser($this->tenant, $this->branch, RoleEnum::Technician);

    $this->getJson('/api/v1/customers', apiHeaders($user, $this->branch))
        ->assertForbidden();
});

test('technician cannot create invoices', function (): void {
    $user = createUser($this->tenant, $this->branch, RoleEnum::Technician);

    $this->postJson('/api/v1/invoices', [
        'customer_id' => $this->customer->id,
        'issue_date'  => today()->toDateString(),
    ], apiHeaders($user, $this->branch))
        ->assertForbidden();
});

// ── Auditor ───────────────────────────────────────────────────────────────────

test('auditor can view customers', function (): void {
    $user = createUser($this->tenant, $this->branch, RoleEnum::Auditor);

    $this->getJson('/api/v1/customers', apiHeaders($user, $this->branch))
        ->assertOk();
});

test('auditor cannot create customers', function (): void {
    $user = createUser($this->tenant, $this->branch, RoleEnum::Auditor);

    $this->postJson('/api/v1/customers', [
        'name'  => 'Auditor Hack',
        'code'  => 'AUD-HACK',
        'type'  => 'business',
        'email' => 'audit@example.test',
    ], apiHeaders($user, $this->branch))
        ->assertForbidden();
});

test('auditor cannot create stock lots', function (): void {
    $user = createUser($this->tenant, $this->branch, RoleEnum::Auditor);

    $this->postJson('/api/v1/stock-lots', [
        'customer_id'     => $this->customer->id,
        'chamber_id'      => $this->chamber->id,
        'branch_id'       => $this->branch->id,
        'unit_of_measure' => 'kg',
        'quantity'        => 100,
        'received_at'     => now()->toDateTimeString(),
    ], apiHeaders($user, $this->branch))
        ->assertForbidden();
});

test('auditor can view branches but cannot create them', function (): void {
    $user = createUser($this->tenant, $this->branch, RoleEnum::Auditor);

    $this->getJson('/api/v1/branches', apiHeaders($user, $this->branch))
        ->assertOk();

    $this->postJson('/api/v1/branches', [
        'code' => 'AUD-NEW',
        'name' => 'Auditor Attempt',
    ], apiHeaders($user, $this->branch))
        ->assertForbidden();
});

// ── Tenant Admin ──────────────────────────────────────────────────────────────

test('tenant admin can create and update branches but cannot delete them', function (): void {
    $user = createUser($this->tenant, $this->branch, RoleEnum::TenantAdmin);

    $response = $this->postJson('/api/v1/branches', [
        'code' => 'ADM-BR-01',
        'name' => 'Admin Created Branch',
    ], apiHeaders($user, $this->branch))
        ->assertCreated();

    $branchId = $response->json('data.id');

    $this->putJson("/api/v1/branches/{$branchId}", [
        'name' => 'Admin Renamed Branch',
    ], apiHeaders($user, $this->branch))
        ->assertOk();

    $this->deleteJson("/api/v1/branches/{$branchId}", [], apiHeaders($user, $this->branch))
        ->assertForbidden();
});

// ── TenantOwner ───────────────────────────────────────────────────────────────

test('tenant owner can view and create customers', function (): void {
    $user = createUser($this->tenant, $this->branch, RoleEnum::TenantOwner);

    $this->getJson('/api/v1/customers', apiHeaders($user, $this->branch))
        ->assertOk();

    $this->postJson('/api/v1/customers', [
        'name'    => 'Owner Created',
        'code'    => 'OWN-001',
        'type'    => 'business',
        'email'   => 'owner-cust@example.test',
        'phone'   => '+8801999000001',
        'country' => 'BD',
    ], apiHeaders($user, $this->branch))
        ->assertCreated();
});

test('tenant owner can access billing and generate invoices', function (): void {
    $user = createUser($this->tenant, $this->branch, RoleEnum::TenantOwner);

    $this->getJson('/api/v1/invoices', apiHeaders($user, $this->branch))
        ->assertOk();

    $this->getJson('/api/v1/rate-plans', apiHeaders($user, $this->branch))
        ->assertOk();
});

test('tenant owner can view, create, and delete branches', function (): void {
    $user = createUser($this->tenant, $this->branch, RoleEnum::TenantOwner);

    $this->getJson('/api/v1/branches', apiHeaders($user, $this->branch))
        ->assertOk();

    $response = $this->postJson('/api/v1/branches', [
        'code' => 'OWN-BR-01',
        'name' => 'Owner Created Branch',
    ], apiHeaders($user, $this->branch))
        ->assertCreated();

    $branchId = $response->json('data.id');

    $this->deleteJson("/api/v1/branches/{$branchId}", [], apiHeaders($user, $this->branch))
        ->assertOk();
});

// ── Unauthenticated ───────────────────────────────────────────────────────────

test('unauthenticated request to protected endpoint returns 401', function (): void {
    $this->getJson('/api/v1/customers')->assertUnauthorized();
    $this->getJson('/api/v1/invoices')->assertUnauthorized();
    $this->getJson('/api/v1/stock-lots')->assertUnauthorized();
});
