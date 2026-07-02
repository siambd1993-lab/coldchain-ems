<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Billing state machine: invoice lifecycle, line-level amount calculation,
 * payment recording and allocation across invoices.
 *
 * All monetary values are poisha (minor units; 1 BDT = 100 poisha). Every
 * multi-step write is wrapped in a database transaction.
 *
 * External callers (controllers) are responsible for permission gates; this
 * service is not context-aware — it operates on whatever models it receives.
 */
final class BillingService
{
    /**
     * Recalculate a line's tax and total from its raw fields, then persist.
     * Call this after every line create/update before syncing invoice totals.
     */
    public function syncLineAmount(InvoiceLine $line): void
    {
        $base = (int) ceil(
            (float) $line->quantity * $line->unit_price_poisha - $line->discount_poisha,
        );
        $base = max(0, $base);

        $taxPoisha = (int) ceil($base * ((float) $line->tax_rate / 100));

        $line->forceFill([
            'tax_poisha'    => $taxPoisha,
            'amount_poisha' => $base + $taxPoisha,
        ])->save();
    }

    /**
     * Recompute the invoice's subtotal / tax / total / amount_due from its lines.
     * Must be called inside the same transaction as any line mutation.
     */
    public function syncInvoiceTotals(Invoice $invoice): void
    {
        $lines = $invoice->lines()->get();

        $subtotal = 0;
        $taxTotal = 0;

        foreach ($lines as $line) {
            $base = max(0, (int) ceil(
                (float) $line->quantity * $line->unit_price_poisha - $line->discount_poisha,
            ));
            $taxTotal += (int) ceil($base * ((float) $line->tax_rate / 100));
            $subtotal += $base;
        }

        $total    = max(0, $subtotal - (int) $invoice->discount_poisha + $taxTotal);
        $amountDue = max(0, $total - (int) $invoice->amount_paid_poisha);

        $invoice->forceFill([
            'subtotal_poisha'  => $subtotal,
            'tax_poisha'       => $taxTotal,
            'total_poisha'     => $total,
            'amount_due_poisha' => $amountDue,
        ])->save();
    }

    /**
     * Add a line to a draft invoice and recompute the invoice totals.
     *
     * @param  array<string, mixed>  $attributes
     * @throws ValidationException when the invoice is not editable
     */
    public function addLine(Invoice $invoice, array $attributes): InvoiceLine
    {
        $this->assertEditable($invoice);

        return DB::transaction(function () use ($invoice, $attributes): InvoiceLine {
            // tenant_id is force-stamped by BelongsToTenant creating hook.
            $line = InvoiceLine::create(array_merge($attributes, [
                'invoice_id' => $invoice->getKey(),
                'tax_poisha' => 0,    // computed below
                'amount_poisha' => 0, // computed below
            ]));

            $this->syncLineAmount($line);
            $this->syncInvoiceTotals($invoice->refresh());

            return $line;
        });
    }

    /**
     * Update a draft invoice line and recompute totals.
     *
     * @param  array<string, mixed>  $attributes
     * @throws ValidationException when the invoice is not editable
     */
    public function updateLine(Invoice $invoice, InvoiceLine $line, array $attributes): InvoiceLine
    {
        $this->assertEditable($invoice);

        return DB::transaction(function () use ($invoice, $line, $attributes): InvoiceLine {
            $line->update($attributes);
            $this->syncLineAmount($line);
            $this->syncInvoiceTotals($invoice->refresh());

            return $line;
        });
    }

    /**
     * Remove a line from a draft invoice and recompute totals.
     *
     * @throws ValidationException when the invoice is not editable
     */
    public function removeLine(Invoice $invoice, InvoiceLine $line): void
    {
        $this->assertEditable($invoice);

        DB::transaction(function () use ($invoice, $line): void {
            $line->delete();
            $this->syncInvoiceTotals($invoice->refresh());
        });
    }

    /**
     * Issue a draft invoice: transitions it to Issued and adjusts the customer's
     * outstanding balance. An invoice must have at least one line to be issued.
     *
     * @throws ValidationException when the invoice cannot be issued
     */
    public function issueInvoice(Invoice $invoice, int $issuedBy): void
    {
        if (! $invoice->status->isEditable()) {
            throw ValidationException::withMessages([
                'invoice' => "Invoice {$invoice->invoice_number} is not a draft and cannot be issued.",
            ]);
        }

        if ($invoice->total_poisha <= 0) {
            throw ValidationException::withMessages([
                'invoice' => 'An invoice must have a positive total before it can be issued.',
            ]);
        }

        DB::transaction(function () use ($invoice, $issuedBy): void {
            $invoice->forceFill([
                'status'     => InvoiceStatus::Issued,
                'issued_at'  => now(),
                'issued_by'  => $issuedBy,
            ])->save();

            // Reflect the outstanding amount in the customer's balance ledger.
            Customer::withoutGlobalScopes()->whereKey($invoice->customer_id)
                ->increment('balance_poisha', $invoice->amount_due_poisha);
        });
    }

    /**
     * Void an invoice: marks it closed and reverses the unpaid portion of the
     * customer's balance. Previously paid amounts are *not* reversed here (use
     * a refund payment for that).
     *
     * @throws ValidationException when the invoice is already void or paid
     */
    public function voidInvoice(Invoice $invoice, string $reason, int $voidedBy): void
    {
        if ($invoice->status === InvoiceStatus::Void) {
            throw ValidationException::withMessages([
                'invoice' => "Invoice {$invoice->invoice_number} is already void.",
            ]);
        }

        DB::transaction(function () use ($invoice, $reason, $voidedBy): void {
            $unpaidAmount = (int) $invoice->amount_due_poisha;

            $invoice->forceFill([
                'status'           => InvoiceStatus::Void,
                'voided_at'        => now(),
                'void_reason'      => $reason,
                'amount_due_poisha' => 0,
            ])->save();

            // Remove the unpaid portion from the customer's outstanding balance.
            if ($unpaidAmount > 0) {
                Customer::withoutGlobalScopes()->whereKey($invoice->customer_id)
                    ->decrement('balance_poisha', $unpaidAmount);
            }
        });
    }

    /**
     * Record a completed payment and optionally allocate slices of it across
     * one or more payable invoices. Returns the persisted Payment.
     *
     * @param  array<string, mixed>             $attributes  validated payment fields
     * @param  array<array{invoice_id:int, amount_poisha:int}>  $allocations
     */
    public function recordPayment(array $attributes, array $allocations, int $tenantId): Payment
    {
        return DB::transaction(function () use ($attributes, $allocations, $tenantId): Payment {
            $number = $this->generatePaymentNumber($tenantId);

            $payment = Payment::create(array_merge($attributes, [
                'payment_number'   => $number,
                'allocated_poisha' => 0,
                'paid_at'          => $attributes['paid_at'] ?? now(),
            ]));

            if ($allocations !== []) {
                $this->applyAllocations($payment, $allocations);
            }

            return $payment->refresh();
        });
    }

    /**
     * Apply (or top-up) allocations from a payment to payable invoices. Can be
     * called after payment creation to wire up additional invoice slices.
     *
     * @param  array<array{invoice_id:int, amount_poisha:int}>  $allocations
     * @throws ValidationException on over-allocation or non-payable invoices
     */
    public function applyAllocations(Payment $payment, array $allocations): void
    {
        DB::transaction(function () use ($payment, $allocations): void {
            // Re-fetch with a write lock so concurrent calls cannot over-allocate.
            $payment = Payment::whereKey($payment->getKey())->lockForUpdate()->firstOrFail();

            $available = $payment->unallocatedAmount();

            foreach ($allocations as $alloc) {
                $invoiceId = (int) $alloc['invoice_id'];
                $requested = (int) $alloc['amount_poisha'];

                if ($requested <= 0) {
                    continue;
                }

                if ($requested > $available) {
                    throw ValidationException::withMessages([
                        'allocations' => sprintf(
                            'Allocation of %d poisha exceeds unallocated balance (%d poisha).',
                            $requested,
                            $available,
                        ),
                    ]);
                }

                $invoice = Invoice::whereKey($invoiceId)->lockForUpdate()->firstOrFail();

                if (! $invoice->status->isPayable()) {
                    throw ValidationException::withMessages([
                        'allocations' => "Invoice {$invoice->invoice_number} is not payable.",
                    ]);
                }

                // Cap at the invoice's remaining due amount.
                $applied = min($requested, (int) $invoice->amount_due_poisha);

                if ($applied <= 0) {
                    continue;
                }

                // Upsert allocation (may top-up an existing partial allocation).
                $existing = PaymentAllocation::where('payment_id', $payment->getKey())
                    ->where('invoice_id', $invoiceId)
                    ->first();

                if ($existing !== null) {
                    $existing->forceFill(['amount_poisha' => $existing->amount_poisha + $applied])->save();
                } else {
                    PaymentAllocation::create([
                        'payment_id'   => $payment->getKey(),
                        'invoice_id'   => $invoiceId,
                        'amount_poisha' => $applied,
                    ]);
                }

                // Update the invoice's paid/due amounts and status.
                $newPaid   = (int) $invoice->amount_paid_poisha + $applied;
                $newDue    = max(0, (int) $invoice->total_poisha - $newPaid);
                $newStatus = $newDue <= 0 ? InvoiceStatus::Paid : InvoiceStatus::PartiallyPaid;

                $invoice->forceFill([
                    'amount_paid_poisha' => $newPaid,
                    'amount_due_poisha'  => $newDue,
                    'status'             => $newStatus,
                ])->save();

                // Reduce the customer's outstanding balance.
                Customer::withoutGlobalScopes()->whereKey($invoice->customer_id)
                    ->decrement('balance_poisha', $applied);

                // Track allocated amount on the payment.
                $payment->forceFill(['allocated_poisha' => $payment->allocated_poisha + $applied])->save();
                $available -= $applied;
            }
        });
    }

    // ── Number generation ────────────────────────────────────────────────────

    /**
     * Generate the next sequential invoice number for this tenant and year.
     * Must be called inside an open write transaction.
     */
    public function generateInvoiceNumber(int $tenantId): string
    {
        $year = now()->year;

        $last = Invoice::withoutBranchScope()
            ->withTrashed()
            ->where('tenant_id', $tenantId)
            ->where('invoice_number', 'like', "INV-{$year}-%")
            ->lockForUpdate()
            ->count();

        return sprintf('INV-%d-%04d', $year, $last + 1);
    }

    /**
     * Generate the next sequential payment receipt number for this tenant.
     * Must be called inside an open write transaction.
     */
    public function generatePaymentNumber(int $tenantId): string
    {
        $year = now()->year;

        $last = Payment::withoutBranchScope()
            ->withTrashed()
            ->where('tenant_id', $tenantId)
            ->where('payment_number', 'like', "RCV-{$year}-%")
            ->lockForUpdate()
            ->count();

        return sprintf('RCV-%d-%04d', $year, $last + 1);
    }

    // ── Guard helpers ────────────────────────────────────────────────────────

    /** @throws ValidationException */
    private function assertEditable(Invoice $invoice): void
    {
        if (! $invoice->status->isEditable()) {
            throw ValidationException::withMessages([
                'invoice' => "Invoice {$invoice->invoice_number} is no longer editable (status: {$invoice->status->value}).",
            ]);
        }
    }
}
