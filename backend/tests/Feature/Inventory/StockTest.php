<?php

declare(strict_types=1);

use App\Enums\Role as RoleEnum;
use App\Models\Chamber;
use App\Models\Customer;
use App\Models\Product;
use App\Models\StockLot;

beforeEach(function (): void {
    ['tenant' => $this->tenant, 'branch' => $this->branch] = createTenant();

    $this->operator = createUser($this->tenant, $this->branch, RoleEnum::Operator);

    $this->product = Product::factory()->create([
        'tenant_id'       => $this->tenant->id,
        'code'            => 'PROD-TST',
        'unit_of_measure' => 'kg',
    ]);

    $this->customer = Customer::factory()->create([
        'tenant_id' => $this->tenant->id,
        'branch_id' => $this->branch->id,
        'code'      => 'CUST-TST',
    ]);

    $this->chamber = Chamber::factory()->create([
        'tenant_id'  => $this->tenant->id,
        'branch_id'  => $this->branch->id,
        'code'       => 'CHM-TST',
        'status'     => 'operational',
    ]);

    $this->headers = apiHeaders($this->operator, $this->branch);
});

// ── Intake ────────────────────────────────────────────────────────────────────

test('operator can intake a stock lot', function (): void {
    $response = $this->postJson('/api/v1/stock-lots', [
        'customer_id'     => $this->customer->id,
        'product_id'      => $this->product->id,
        'chamber_id'      => $this->chamber->id,
        'branch_id'       => $this->branch->id,
        'unit_of_measure' => 'kg',
        'quantity'        => 500.0,
        'received_at'     => now()->toDateTimeString(),
    ], $this->headers);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'in_storage')
        ->assertJsonPath('data.quantity', '500.000');

    $this->assertDatabaseHas('stock_lots', [
        'tenant_id'   => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'status'      => 'in_storage',
    ]);
    $this->assertDatabaseHas('stock_movements', [
        'tenant_id' => $this->tenant->id,
        'type'      => 'stock_in',
        'quantity'  => 500.0,
    ]);
});

test('intake generates unique lot code per branch', function (): void {
    $r1 = $this->postJson('/api/v1/stock-lots', [
        'customer_id'     => $this->customer->id,
        'product_id'      => $this->product->id,
        'chamber_id'      => $this->chamber->id,
        'branch_id'       => $this->branch->id,
        'unit_of_measure' => 'kg',
        'quantity'        => 100.0,
        'received_at'     => now()->toDateTimeString(),
    ], $this->headers)->assertCreated();

    $r2 = $this->postJson('/api/v1/stock-lots', [
        'customer_id'     => $this->customer->id,
        'product_id'      => $this->product->id,
        'chamber_id'      => $this->chamber->id,
        'branch_id'       => $this->branch->id,
        'unit_of_measure' => 'kg',
        'quantity'        => 200.0,
        'received_at'     => now()->toDateTimeString(),
    ], $this->headers)->assertCreated();

    expect($r1->json('data.lot_code'))->not->toBe($r2->json('data.lot_code'));
});

test('intake requires quantity > 0', function (): void {
    $this->postJson('/api/v1/stock-lots', [
        'customer_id'     => $this->customer->id,
        'chamber_id'      => $this->chamber->id,
        'branch_id'       => $this->branch->id,
        'unit_of_measure' => 'kg',
        'quantity'        => 0,
        'received_at'     => now()->toDateTimeString(),
    ], $this->headers)->assertUnprocessable();
});

// ── Release ───────────────────────────────────────────────────────────────────

test('operator can release stock from a lot', function (): void {
    // Intake first.
    $intakeResp = $this->postJson('/api/v1/stock-lots', [
        'customer_id'     => $this->customer->id,
        'product_id'      => $this->product->id,
        'chamber_id'      => $this->chamber->id,
        'branch_id'       => $this->branch->id,
        'unit_of_measure' => 'kg',
        'quantity'        => 1_000.0,
        'received_at'     => now()->toDateTimeString(),
    ], $this->headers)->assertCreated();

    $lotId = $intakeResp->json('data.id');

    // Partial release.
    $releaseResp = $this->postJson("/api/v1/stock-lots/{$lotId}/release", [
        'quantity'   => 400.0,
        'notes'      => 'Partial release to customer truck.',
    ], apiHeaders(createUser($this->tenant, $this->branch, RoleEnum::Operator), $this->branch));

    $releaseResp->assertOk();

    $lot = StockLot::find($lotId);
    expect((float) $lot->quantity)->toBe(600.0);
    expect($lot->status)->toBe('partially_released');
});

test('release more than available quantity is rejected', function (): void {
    $intakeResp = $this->postJson('/api/v1/stock-lots', [
        'customer_id'     => $this->customer->id,
        'product_id'      => $this->product->id,
        'chamber_id'      => $this->chamber->id,
        'branch_id'       => $this->branch->id,
        'unit_of_measure' => 'kg',
        'quantity'        => 100.0,
        'received_at'     => now()->toDateTimeString(),
    ], $this->headers)->assertCreated();

    $lotId = $intakeResp->json('data.id');

    $this->postJson("/api/v1/stock-lots/{$lotId}/release", [
        'quantity' => 9_999.0,
    ], $this->headers)->assertUnprocessable();
});

// ── Adjustment ────────────────────────────────────────────────────────────────

test('operator can adjust quantity with a positive delta', function (): void {
    $intakeResp = $this->postJson('/api/v1/stock-lots', [
        'customer_id'     => $this->customer->id,
        'product_id'      => $this->product->id,
        'chamber_id'      => $this->chamber->id,
        'branch_id'       => $this->branch->id,
        'unit_of_measure' => 'kg',
        'quantity'        => 500.0,
        'received_at'     => now()->toDateTimeString(),
    ], $this->headers)->assertCreated();

    $lotId = $intakeResp->json('data.id');

    $user = createUser($this->tenant, $this->branch, RoleEnum::TenantAdmin);
    $this->postJson("/api/v1/stock-lots/{$lotId}/adjust", [
        'delta'  => 50.0,
        'reason' => 'Reweigh after moisture loss correction',
    ], apiHeaders($user, $this->branch))->assertOk();

    expect((float) StockLot::find($lotId)->quantity)->toBe(550.0);
});

test('adjustment resulting in negative quantity is rejected', function (): void {
    $intakeResp = $this->postJson('/api/v1/stock-lots', [
        'customer_id'     => $this->customer->id,
        'product_id'      => $this->product->id,
        'chamber_id'      => $this->chamber->id,
        'branch_id'       => $this->branch->id,
        'unit_of_measure' => 'kg',
        'quantity'        => 100.0,
        'received_at'     => now()->toDateTimeString(),
    ], $this->headers)->assertCreated();

    $lotId = $intakeResp->json('data.id');

    $user = createUser($this->tenant, $this->branch, RoleEnum::TenantAdmin);
    $this->postJson("/api/v1/stock-lots/{$lotId}/adjust", [
        'delta'  => -999.0,
        'reason' => 'Bad data',
    ], apiHeaders($user, $this->branch))->assertUnprocessable();
});

// ── List & show ───────────────────────────────────────────────────────────────

test('operator can list and show stock lots', function (): void {
    $lot = StockLot::factory()->create([
        'tenant_id'   => $this->tenant->id,
        'branch_id'   => $this->branch->id,
        'customer_id' => $this->customer->id,
    ]);

    $this->getJson('/api/v1/stock-lots', $this->headers)
        ->assertOk()
        ->assertJsonStructure(['data', 'meta']);

    $this->getJson("/api/v1/stock-lots/{$lot->id}", $this->headers)
        ->assertOk()
        ->assertJsonPath('data.id', $lot->id);
});

test('movements sub-resource is accessible', function (): void {
    $intakeResp = $this->postJson('/api/v1/stock-lots', [
        'customer_id'     => $this->customer->id,
        'product_id'      => $this->product->id,
        'chamber_id'      => $this->chamber->id,
        'branch_id'       => $this->branch->id,
        'unit_of_measure' => 'kg',
        'quantity'        => 300.0,
        'received_at'     => now()->toDateTimeString(),
    ], $this->headers)->assertCreated();

    $lotId = $intakeResp->json('data.id');

    $this->getJson("/api/v1/stock-lots/{$lotId}/movements", $this->headers)
        ->assertOk()
        ->assertJsonCount(1, 'data'); // initial stock_in movement
});
