<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inventory:
 *   stock_lots      → a batch of a customer's goods occupying storage.
 *   stock_movements → an append-only ledger of every in/out/transfer/adjust
 *                     event. The lot's live quantity is the sum of its
 *                     movements; `balance_after` snapshots the running total for
 *                     audit and fast statements.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_lots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();

            $table->string('lot_code');
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('chamber_id')->nullable()->constrained('chambers')->nullOnDelete();
            $table->foreignId('storage_unit_id')->nullable()->constrained('storage_units')->nullOnDelete();
            $table->foreignId('rate_plan_id')->nullable();

            $table->string('description')->nullable();
            $table->enum('status', ['in_storage', 'partially_released', 'released', 'disposed'])
                ->default('in_storage');

            $table->string('unit_of_measure', 16)->default('kg');
            $table->decimal('initial_quantity', 14, 3)->default(0);
            $table->decimal('quantity', 14, 3)->default(0);       // live quantity remaining
            $table->decimal('initial_weight_kg', 14, 3)->nullable();
            $table->decimal('weight_kg', 14, 3)->nullable();      // live weight remaining
            $table->unsignedInteger('package_count')->nullable();

            $table->string('grade')->nullable();
            $table->string('marks')->nullable(); // identifying marks on packages

            $table->timestamp('received_at')->nullable();
            $table->date('expected_release_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->date('expiry_date')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['branch_id', 'lot_code']);
            $table->index(['tenant_id', 'branch_id', 'status']);
            $table->index(['customer_id', 'status']);
            $table->index('chamber_id');
        });

        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('lot_id')->constrained('stock_lots')->cascadeOnDelete();

            $table->enum('type', ['stock_in', 'stock_out', 'transfer', 'adjustment']);
            $table->decimal('quantity', 14, 3);                 // magnitude in the lot's UOM
            $table->decimal('weight_kg', 14, 3)->nullable();
            $table->unsignedInteger('package_count')->nullable();
            $table->decimal('balance_after', 14, 3)->nullable(); // running lot quantity after event

            // Movement endpoints (transfers populate both from/to).
            $table->foreignId('from_chamber_id')->nullable()->constrained('chambers')->nullOnDelete();
            $table->foreignId('from_storage_unit_id')->nullable()->constrained('storage_units')->nullOnDelete();
            $table->foreignId('to_chamber_id')->nullable()->constrained('chambers')->nullOnDelete();
            $table->foreignId('to_storage_unit_id')->nullable()->constrained('storage_units')->nullOnDelete();

            $table->string('reference')->nullable(); // gate pass / challan number
            $table->string('reason')->nullable();    // required for adjustments
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at');

            $table->json('metadata')->nullable(); // proof photo paths, weighbridge slip, etc.

            $table->timestamps();

            $table->index(['tenant_id', 'branch_id', 'occurred_at']);
            $table->index(['lot_id', 'occurred_at']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_lots');
    }
};
