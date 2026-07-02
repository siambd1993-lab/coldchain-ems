<?php

declare(strict_types=1);

use App\Enums\Role as RoleEnum;
use App\Models\Alert;
use App\Models\Chamber;
use App\Models\Customer;
use App\Models\Device;
use App\Models\EnergyConsumption;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\StockLot;

beforeEach(function (): void {
    ['tenant' => $this->tenant, 'branch' => $this->branch] = createTenant();
    $this->owner   = createUser($this->tenant, $this->branch, RoleEnum::TenantOwner);
    $this->headers = apiHeaders($this->owner, $this->branch);
});

// ── Reports ───────────────────────────────────────────────────────────────────

test('revenue report reconciles billed and collected totals', function (): void {
    $customer = Customer::factory()->create([
        'tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id,
    ]);

    Invoice::factory()->create([
        'tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id,
        'customer_id' => $customer->id, 'status' => 'issued',
        'total_poisha' => 150_000, 'amount_due_poisha' => 150_000,
        'issued_at' => now()->subDays(3),
    ]);
    Payment::factory()->create([
        'tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id,
        'customer_id' => $customer->id, 'status' => 'completed',
        'amount_poisha' => 60_000, 'paid_at' => now()->subDay(),
    ]);

    $this->getJson('/api/v1/reports/revenue', $this->headers)
        ->assertOk()
        ->assertJsonPath('data.total_billed_poisha', 150_000)
        ->assertJsonPath('data.total_collected_poisha', 60_000);
});

test('occupancy report shows per-chamber fill', function (): void {
    $chamber = Chamber::factory()->create([
        'tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id,
        'capacity_weight_kg' => 1_000,
    ]);
    $customer = Customer::factory()->create([
        'tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id,
    ]);
    StockLot::factory()->create([
        'tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id,
        'customer_id' => $customer->id, 'chamber_id' => $chamber->id,
        'status' => 'in_storage', 'weight_kg' => 250,
    ]);

    $resp = $this->getJson('/api/v1/reports/occupancy', $this->headers)->assertOk();

    $row = collect($resp->json('data'))->firstWhere('chamber_id', $chamber->id);
    expect($row['lots'])->toBe(1)
        ->and((float) $row['occupancy_pct'])->toEqual(25.0);
});

test('receivables report buckets dues by age', function (): void {
    $customer = Customer::factory()->create([
        'tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id,
    ]);
    Invoice::factory()->create([
        'tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id,
        'customer_id' => $customer->id, 'status' => 'issued',
        'total_poisha' => 80_000, 'amount_due_poisha' => 80_000,
        'issued_at' => now()->subDays(45),
    ]);

    $this->getJson('/api/v1/reports/receivables', $this->headers)
        ->assertOk()
        ->assertJsonPath('data.total_due_poisha', 80_000)
        ->assertJsonPath('data.aging.31_60', 80_000)
        ->assertJsonPath('data.customers.0.customer_id', $customer->id);
});

test('operator is forbidden from reports', function (): void {
    $operator = createUser($this->tenant, $this->branch, RoleEnum::Operator);

    $this->getJson('/api/v1/reports/revenue', apiHeaders($operator, $this->branch))
        ->assertForbidden();
});

// ── Audit log ─────────────────────────────────────────────────────────────────

test('audit log records actions and is filterable', function (): void {
    // Produce an audited action first.
    $this->postJson('/api/v1/customers', [
        'code' => 'C-AUD', 'type' => 'individual', 'name' => 'Audit Target',
    ], $this->headers)->assertCreated();

    $resp = $this->getJson('/api/v1/audit-logs?action=customer.created', $this->headers)
        ->assertOk();

    expect($resp->json('data.0.action'))->toBe('customer.created')
        ->and($resp->json('data.0.actor_label'))->toBe($this->owner->email);
});

// ── IoT: devices, alerts, energy ──────────────────────────────────────────────

test('devices can be registered, listed and updated', function (): void {
    $chamber = Chamber::factory()->create([
        'tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id,
    ]);

    $created = $this->postJson('/api/v1/devices', [
        'device_uid' => 'SENS-T1', 'name' => 'Test Sensor',
        'device_type' => 'sensor', 'protocol' => 'mqtt',
        'branch_id' => $this->branch->id, 'chamber_id' => $chamber->id,
    ], $this->headers)->assertCreated();

    $this->getJson('/api/v1/devices', $this->headers)
        ->assertOk()
        ->assertJsonPath('data.0.device_uid', 'SENS-T1');

    $this->putJson("/api/v1/devices/{$created->json('data.id')}", [
        'status' => 'fault',
    ], $this->headers)->assertOk()->assertJsonPath('data.status', 'fault');
});

test('alerts can be acknowledged and resolved', function (): void {
    $alert = Alert::create([
        'tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id,
        'alert_type' => 'temperature_high', 'severity' => 'critical',
        'status' => 'active', 'title' => 'Too warm',
        'triggered_at' => now()->subMinutes(10),
    ]);

    $this->postJson("/api/v1/alerts/{$alert->id}/acknowledge", [], $this->headers)
        ->assertOk()
        ->assertJsonPath('data.status', 'acknowledged');

    $this->postJson("/api/v1/alerts/{$alert->id}/resolve", [
        'resolution_note' => 'Compressor restarted.',
    ], $this->headers)
        ->assertOk()
        ->assertJsonPath('data.status', 'resolved')
        ->assertJsonPath('data.resolution_note', 'Compressor restarted.');
});

test('energy summary aggregates by source', function (): void {
    EnergyConsumption::create([
        'tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id,
        'date' => now()->subDay()->toDateString(), 'source' => 'grid',
        'energy_kwh' => 100, 'cost_poisha' => 95_000, 'co2_kg' => 67,
    ]);
    EnergyConsumption::create([
        'tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id,
        'date' => now()->subDay()->toDateString(), 'source' => 'solar',
        'energy_kwh' => 50, 'cost_poisha' => 0, 'co2_kg' => 0,
    ]);

    $resp = $this->getJson('/api/v1/energy/summary', $this->headers)->assertOk();

    expect($resp->json('data.total_kwh'))->toBe(150)
        ->and($resp->json('data.solar_share_pct'))->toBe(33.3)
        ->and(collect($resp->json('data.by_source'))->pluck('source'))
        ->toContain('grid', 'solar');
});

test('cross-tenant device is invisible', function (): void {
    ['tenant' => $tenantB, 'branch' => $branchB] = createTenant('other-tenant');
    $foreign = Device::create([
        'tenant_id' => $tenantB->id, 'branch_id' => $branchB->id,
        'device_uid' => 'FOREIGN-1', 'name' => 'Not yours',
        'device_type' => 'sensor', 'status' => 'online',
    ]);

    $this->getJson("/api/v1/devices/{$foreign->id}", $this->headers)->assertNotFound();
});

test('live energy flow returns a balanced simulated state', function (): void {
    $resp = $this->getJson('/api/v1/energy/live', $this->headers)->assertOk();

    expect($resp->json('data.mode'))->toBe('simulated')
        ->and($resp->json('data.load_kw'))->toBeGreaterThan(0)
        ->and($resp->json('data.battery.soc_pct'))->toBeGreaterThanOrEqual(0)
        ->and($resp->json('data.battery.soc_pct'))->toBeLessThanOrEqual(100);

    // Every reported flow moves real power.
    foreach ($resp->json('data.flows') as $flow) {
        expect($flow['kw'])->toBeGreaterThan(0);
    }
});

test('energy insights produce peak-shift and anomaly findings', function (): void {
    // 14 normal grid days + one wild spike yesterday.
    for ($d = 15; $d >= 2; $d--) {
        EnergyConsumption::create([
            'tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id,
            'date' => now()->subDays($d)->toDateString(), 'source' => 'grid',
            'energy_kwh' => 100, 'cost_poisha' => 95_000,
        ]);
    }
    EnergyConsumption::create([
        'tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id,
        'date' => now()->subDay()->toDateString(), 'source' => 'grid',
        'energy_kwh' => 400, 'cost_poisha' => 380_000,
    ]);

    $resp  = $this->getJson('/api/v1/energy/insights', $this->headers)->assertOk();
    $types = collect($resp->json('data.insights'))->pluck('type');

    expect($types)->toContain('anomaly')->and($types)->toContain('peak_shift');

    $peak = collect($resp->json('data.insights'))->firstWhere('type', 'peak_shift');
    expect($peak['saving_poisha_monthly'])->toBeGreaterThan(0);
});

test('operator is forbidden from energy endpoints', function (): void {
    $operator = createUser($this->tenant, $this->branch, RoleEnum::Operator);

    $this->getJson('/api/v1/energy/live', apiHeaders($operator, $this->branch))
        ->assertForbidden();
});
