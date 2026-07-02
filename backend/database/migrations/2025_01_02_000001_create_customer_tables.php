<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customers (depositors who rent storage) and the product/commodity catalogue.
 *
 * Customers double as portal principals (separate `customers` auth guard), so
 * they carry an optional password. All money is stored in poisha (1 BDT = 100).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();

            $table->string('code');
            $table->enum('type', ['individual', 'business'])->default('business');
            $table->string('name');
            $table->string('contact_person')->nullable();

            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('password')->nullable(); // portal login (optional)

            // KYC / compliance.
            $table->string('national_id')->nullable();
            $table->string('tax_id')->nullable();       // TIN/BIN
            $table->string('trade_license')->nullable();

            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city')->nullable();
            $table->string('district')->nullable();
            $table->char('country', 2)->default('BD');

            $table->bigInteger('credit_limit_poisha')->default(0);
            $table->bigInteger('opening_balance_poisha')->default(0);
            // Outstanding receivable (denormalised): positive = customer owes us.
            $table->bigInteger('balance_poisha')->default(0);

            $table->enum('status', ['active', 'inactive', 'blocked'])->default('active');
            $table->text('notes')->nullable();
            $table->json('settings')->nullable();

            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'code']);
            $table->unique(['tenant_id', 'email']);
            $table->index(['tenant_id', 'branch_id', 'status']);
            $table->index('phone');
        });

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();

            $table->string('code');
            $table->string('name');
            $table->string('category')->nullable();
            $table->enum('unit_of_measure', ['kg', 'ton', 'crate', 'bag', 'carton', 'piece', 'pallet'])
                ->default('kg');

            // Recommended storage envelope for this commodity.
            $table->decimal('default_temp_min_c', 5, 2)->nullable();
            $table->decimal('default_temp_max_c', 5, 2)->nullable();
            $table->unsignedInteger('shelf_life_days')->nullable();

            $table->string('hs_code')->nullable();
            $table->json('attributes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
        Schema::dropIfExists('customers');
    }
};
