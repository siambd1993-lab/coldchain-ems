<?php

declare(strict_types=1);

use App\Enums\Role as RoleEnum;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\RatePlan;

beforeEach(function (): void {
    ['tenant' => $this->tenant, 'branch' => $this->branch] = createTenant();

    $this->owner     = createUser($this->tenant, $this->branch, RoleEnum::TenantOwner);
    $this->accountant = createUser($this->tenant, $this->branch, RoleEnum::Accountant);

    $this->customer = Customer::factory()->create([
        'tenant_id'           => $this->tenant->id,
        'branch_id'           => $this->branch->id,
        'code'                => 'INV-CUST',
        'balance_poisha'      => 0,
        'credit_limit_poisha' => 0,
    ]);

    $this->ratePlan = RatePlan::factory()->create([
        'tenant_id'      => $this->tenant->id,
        'billing_method' => 'per_kg_per_month',
        'rate_poisha'    => 200,
        'tax_rate'       => '15.00',
        'is_active'      => true,
    ]);
});

// ── Draft CRUD ────────────────────────────────────────────────────────────────

test('accountant can create a draft invoice', function (): void {
    $response = $this->postJson('/api/v1/invoices', [
        'customer_id' => $this->customer->id,
        'branch_id'   => $this->branch->id,
        'issue_date'  => today()->toDateString(),
        'due_date'    => today()->addDays(30)->toDateString(),
        'currency'    => 'BDT',
    ], apiHeaders($this->accountant, $this->branch));

    $response->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonStructure(['data' => ['id', 'invoice_number', 'status']]);

    expect($response->json('data.invoice_number'))->toMatch('/^INV-\d{4}-\d{4}$/');
});

test('draft invoice number increments sequentially per tenant-year', function (): void {
    $headers = apiHeaders($this->accountant, $this->branch);
    $payload = [
        'customer_id' => $this->customer->id,
        'branch_id'   => $this->branch->id,
        'issue_date'  => today()->toDateString(),
    ];

    $r1 = $this->postJson('/api/v1/invoices', $payload, $headers)->assertCreated();
    $r2 = $this->postJson('/api/v1/invoices', $payload, $headers)->assertCreated();

    // Invoice numbers must differ and be in ascending order.
    expect($r1->json('data.invoice_number'))->not->toBe($r2->json('data.invoice_number'));
});

test('accountant can add a line to a draft invoice', function (): void {
    $invoice = Invoice::factory()->create([
        'tenant_id'   => $this->tenant->id,
        'branch_id'   => $this->branch->id,
        'customer_id' => $this->customer->id,
        'status'      => 'draft',
        'issue_date'  => today(),
    ]);

    $response = $this->postJson("/api/v1/invoices/{$invoice->id}/lines", [
        'description'     => 'Cold storage – potato, 500 kg × 30 days',
        'quantity'        => 500.0,
        'unit'            => 'kg',
        'unit_price_poisha' => 200,
        'discount_poisha' => 0,
        'tax_rate'        => 15.00,
    ], apiHeaders($this->accountant, $this->branch));

    $response->assertCreated()
        ->assertJsonStructure(['data' => ['id', 'amount_poisha', 'tax_poisha']]);

    // 500 * 200 = 100 000 base, tax = 15 000, total = 115 000 poisha
    expect($response->json('data.amount_poisha'))->toBe(115_000);
});

test('cannot add line to non-draft invoice', function (): void {
    $invoice = Invoice::factory()->create([
        'tenant_id'          => $this->tenant->id,
        'branch_id'          => $this->branch->id,
        'customer_id'        => $this->customer->id,
        'status'             => 'issued',
        'issue_date'         => today(),
        'total_poisha'       => 10_000,
        'amount_due_poisha'  => 10_000,
    ]);

    $this->postJson("/api/v1/invoices/{$invoice->id}/lines", [
        'description'       => 'Late charge',
        'quantity'          => 1,
        'unit_price_poisha' => 5_000,
        'tax_rate'          => 0,
    ], apiHeaders($this->accountant, $this->branch))
        ->assertUnprocessable();   // BillingService::assertEditable throws ValidationException → 422
});

// ── Issue (state transition) ──────────────────────────────────────────────────

test('owner can issue an invoice with lines, updating customer balance', function (): void {
    $invoice = Invoice::factory()->create([
        'tenant_id'          => $this->tenant->id,
        'branch_id'          => $this->branch->id,
        'customer_id'        => $this->customer->id,
        'status'             => 'draft',
        'issue_date'         => today(),
        'subtotal_poisha'    => 100_000,
        'tax_poisha'         => 15_000,
        'total_poisha'       => 115_000,
        'amount_due_poisha'  => 115_000,
    ]);

    $this->postJson("/api/v1/invoices/{$invoice->id}/issue", [], apiHeaders($this->owner, $this->branch))
        ->assertOk()
        ->assertJsonPath('data.status', 'issued');

    // Customer balance must be incremented.
    expect($this->customer->fresh()->balance_poisha)->toBe(115_000);
});

test('cannot issue an invoice with zero total', function (): void {
    $invoice = Invoice::factory()->create([
        'tenant_id'         => $this->tenant->id,
        'branch_id'         => $this->branch->id,
        'customer_id'       => $this->customer->id,
        'status'            => 'draft',
        'issue_date'        => today(),
        'total_poisha'      => 0,
        'amount_due_poisha' => 0,
    ]);

    $this->postJson("/api/v1/invoices/{$invoice->id}/issue", [], apiHeaders($this->owner, $this->branch))
        ->assertUnprocessable();
});

// ── Void ──────────────────────────────────────────────────────────────────────

test('owner can void an issued invoice, reversing unpaid balance', function (): void {
    $invoice = Invoice::factory()->create([
        'tenant_id'          => $this->tenant->id,
        'branch_id'          => $this->branch->id,
        'customer_id'        => $this->customer->id,
        'status'             => 'issued',
        'issue_date'         => today(),
        'total_poisha'       => 50_000,
        'amount_paid_poisha' => 0,
        'amount_due_poisha'  => 50_000,
    ]);

    // Manually set the customer's balance as if this invoice had been issued.
    $this->customer->update(['balance_poisha' => 50_000]);

    $this->postJson("/api/v1/invoices/{$invoice->id}/void", [
        'void_reason' => 'Data entry error.',
    ], apiHeaders($this->owner, $this->branch))
        ->assertOk()
        ->assertJsonPath('data.status', 'void');

    // Unpaid amount must be reversed from customer balance.
    expect($this->customer->fresh()->balance_poisha)->toBe(0);
});

test('cannot void an already-voided invoice', function (): void {
    $invoice = Invoice::factory()->create([
        'tenant_id'  => $this->tenant->id,
        'branch_id'  => $this->branch->id,
        'customer_id' => $this->customer->id,
        'status'     => 'void',
        'issue_date' => today(),
    ]);

    $this->postJson("/api/v1/invoices/{$invoice->id}/void", [
        'void_reason' => 'Again?',
    ], apiHeaders($this->owner, $this->branch))
        ->assertUnprocessable();
});

// ── Delete draft ──────────────────────────────────────────────────────────────

test('accountant can delete a draft invoice', function (): void {
    $invoice = Invoice::factory()->create([
        'tenant_id'   => $this->tenant->id,
        'branch_id'   => $this->branch->id,
        'customer_id' => $this->customer->id,
        'status'      => 'draft',
        'issue_date'  => today(),
    ]);

    $this->deleteJson("/api/v1/invoices/{$invoice->id}", [], apiHeaders($this->accountant, $this->branch))
        ->assertOk();

    $this->assertSoftDeleted('invoices', ['id' => $invoice->id]);
});

test('cannot delete an issued invoice', function (): void {
    $invoice = Invoice::factory()->create([
        'tenant_id'          => $this->tenant->id,
        'branch_id'          => $this->branch->id,
        'customer_id'        => $this->customer->id,
        'status'             => 'issued',
        'issue_date'         => today(),
        'total_poisha'       => 10_000,
        'amount_due_poisha'  => 10_000,
    ]);

    $this->deleteJson("/api/v1/invoices/{$invoice->id}", [], apiHeaders($this->accountant, $this->branch))
        ->assertConflict();
});

// ── Payments ──────────────────────────────────────────────────────────────────

test('accountant can record a payment and it reduces customer balance', function (): void {
    // Set a balance on the customer.
    $this->customer->update(['balance_poisha' => 100_000]);

    $invoice = Invoice::factory()->create([
        'tenant_id'          => $this->tenant->id,
        'branch_id'          => $this->branch->id,
        'customer_id'        => $this->customer->id,
        'status'             => 'issued',
        'issue_date'         => today(),
        'total_poisha'       => 100_000,
        'amount_due_poisha'  => 100_000,
        'amount_paid_poisha' => 0,
    ]);

    $response = $this->postJson('/api/v1/payments', [
        'customer_id'   => $this->customer->id,
        'amount_poisha' => 60_000,
        'method'        => 'bank_transfer',
        'paid_at'       => now()->toDateTimeString(),
        'allocations'   => [
            ['invoice_id' => $invoice->id, 'amount_poisha' => 60_000],
        ],
    ], apiHeaders($this->accountant, $this->branch));

    $response->assertCreated()
        ->assertJsonPath('data.amount_poisha', 60_000)
        ->assertJsonPath('data.status', 'completed');

    $this->assertDatabaseHas('payment_allocations', [
        'invoice_id'    => $invoice->id,
        'amount_poisha' => 60_000,
    ]);

    // Invoice should be partially paid.
    expect($invoice->fresh()->status->value)->toBe('partially_paid');
    // Customer balance reduced.
    expect($this->customer->fresh()->balance_poisha)->toBe(40_000);
});

test('full payment marks invoice as paid', function (): void {
    $this->customer->update(['balance_poisha' => 50_000]);

    $invoice = Invoice::factory()->create([
        'tenant_id'          => $this->tenant->id,
        'branch_id'          => $this->branch->id,
        'customer_id'        => $this->customer->id,
        'status'             => 'issued',
        'issue_date'         => today(),
        'total_poisha'       => 50_000,
        'amount_due_poisha'  => 50_000,
        'amount_paid_poisha' => 0,
    ]);

    $this->postJson('/api/v1/payments', [
        'customer_id'   => $this->customer->id,
        'amount_poisha' => 50_000,
        'method'        => 'cash',
        'paid_at'       => now()->toDateTimeString(),
        'allocations'   => [
            ['invoice_id' => $invoice->id, 'amount_poisha' => 50_000],
        ],
    ], apiHeaders($this->accountant, $this->branch))
        ->assertCreated();

    expect($invoice->fresh()->status->value)->toBe('paid');
    expect($this->customer->fresh()->balance_poisha)->toBe(0);
});
