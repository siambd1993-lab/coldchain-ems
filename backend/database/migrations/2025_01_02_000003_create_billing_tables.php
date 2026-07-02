<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Billing & receivables. All monetary columns are BIGINT poisha (minor units;
 * 1 BDT = 100 poisha) to avoid floating-point drift.
 *
 *   rate_plans          → tariff definitions (per-kg/day, per-slot/month, …).
 *   invoices/lines      → issued charges.
 *   payments            → money received (cash or MFS gateway).
 *   payment_allocations → how a payment is split across invoices.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rate_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();

            $table->string('code');
            $table->string('name');
            $table->enum('billing_method', [
                'per_kg_per_day', 'per_kg_per_month',
                'per_slot_per_day', 'per_slot_per_month',
                'per_pallet_per_day', 'per_pallet_per_month',
                'flat_monthly',
            ])->default('per_kg_per_month');

            $table->bigInteger('rate_poisha')->default(0);            // per unit per period
            $table->bigInteger('minimum_charge_poisha')->default(0);
            $table->string('unit_of_measure', 16)->nullable();
            $table->unsignedInteger('grace_days')->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);            // percent, e.g. 15.00 = VAT

            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'is_active']);
        });

        // Deferred FK: stock_lots.rate_plan_id was created (unconstrained) in an
        // earlier migration since rate_plans did not yet exist. Wire it now.
        Schema::table('stock_lots', function (Blueprint $table): void {
            $table->foreign('rate_plan_id')->references('id')->on('rate_plans')->nullOnDelete();
            $table->index('rate_plan_id');
        });

        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();

            $table->string('invoice_number');
            $table->enum('status', ['draft', 'issued', 'partially_paid', 'paid', 'overdue', 'void'])
                ->default('draft');

            $table->date('issue_date');
            $table->date('due_date')->nullable();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();

            $table->char('currency', 3)->default('BDT');
            $table->bigInteger('subtotal_poisha')->default(0);
            $table->bigInteger('discount_poisha')->default(0);
            $table->bigInteger('tax_poisha')->default(0);
            $table->bigInteger('total_poisha')->default(0);
            $table->bigInteger('amount_paid_poisha')->default(0);
            $table->bigInteger('amount_due_poisha')->default(0); // total - paid, maintained on write

            $table->text('notes')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'invoice_number']);
            $table->index(['tenant_id', 'branch_id', 'status']);
            $table->index(['customer_id', 'status']);
            $table->index('due_date');
        });

        Schema::create('invoice_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('lot_id')->nullable()->constrained('stock_lots')->nullOnDelete();
            $table->foreignId('rate_plan_id')->nullable()->constrained('rate_plans')->nullOnDelete();

            $table->string('description');
            $table->decimal('quantity', 14, 3)->default(1);
            $table->string('unit', 16)->nullable();
            $table->bigInteger('unit_price_poisha')->default(0);
            $table->bigInteger('discount_poisha')->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->bigInteger('tax_poisha')->default(0);
            $table->bigInteger('amount_poisha')->default(0); // (qty*unit_price - discount) + tax

            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index('invoice_id');
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();

            $table->string('payment_number');
            $table->enum('method', [
                'cash', 'bkash', 'nagad', 'bank_transfer', 'card', 'cheque', 'adjustment',
            ])->default('cash');
            $table->enum('status', ['pending', 'completed', 'failed', 'refunded', 'cancelled'])
                ->default('completed');

            $table->char('currency', 3)->default('BDT');
            $table->bigInteger('amount_poisha');
            $table->bigInteger('allocated_poisha')->default(0); // portion applied to invoices

            $table->string('reference')->nullable(); // gateway txn id / cheque no
            $table->string('gateway')->nullable();    // bkash|nagad|…
            $table->json('gateway_payload')->nullable();

            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'payment_number']);
            $table->index(['tenant_id', 'branch_id', 'status']);
            $table->index(['customer_id', 'status']);
            $table->index(['gateway', 'reference']);
        });

        Schema::create('payment_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->bigInteger('amount_poisha');
            $table->timestamps();

            $table->unique(['payment_id', 'invoice_id']);
            $table->index('invoice_id');
        });
    }

    public function down(): void
    {
        // Drop the deferred FK first so rate_plans can be removed.
        if (Schema::hasTable('stock_lots')) {
            Schema::table('stock_lots', function (Blueprint $table): void {
                $table->dropForeign(['rate_plan_id']);
            });
        }

        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('rate_plans');
    }
};
