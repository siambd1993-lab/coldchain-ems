<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Energy management and the immutable audit trail.
 *
 *   energy_tariffs      → utility/source rate schedules (grid, generator, solar).
 *   energy_consumptions → daily aggregated kWh + cost per branch/chamber/source.
 *   audit_logs          → append-only record of security- and money-relevant
 *                         actions across the platform.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('energy_tariffs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->cascadeOnDelete();

            $table->string('name');
            $table->enum('source', ['grid', 'generator', 'solar', 'battery'])->default('grid');
            $table->bigInteger('rate_poisha');           // per kWh
            $table->decimal('demand_charge_poisha', 16, 0)->nullable(); // per kW of peak demand
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->json('time_of_use')->nullable();      // peak/off-peak bands
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'branch_id', 'is_active']);
        });

        Schema::create('energy_consumptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('chamber_id')->nullable()->constrained('chambers')->nullOnDelete();
            $table->foreignId('device_id')->nullable()->constrained('devices')->nullOnDelete();

            $table->date('date');
            $table->enum('source', ['grid', 'generator', 'solar', 'mixed'])->default('grid');
            $table->decimal('energy_kwh', 14, 4)->default(0);
            $table->decimal('peak_demand_kw', 12, 4)->nullable();
            $table->bigInteger('cost_poisha')->default(0);
            $table->decimal('co2_kg', 12, 3)->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->unique(['branch_id', 'chamber_id', 'date', 'source'], 'energy_daily_unique');
            $table->index(['tenant_id', 'branch_id', 'date']);
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();

            // Actor: usually a user, but may be a customer, device, or the system.
            $table->enum('actor_type', ['user', 'customer', 'device', 'system'])->default('user');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_label')->nullable(); // denormalised name/email for readability

            $table->string('action'); // dot-notation, e.g. "invoice.voided"
            $table->nullableMorphs('auditable');
            $table->string('description')->nullable();

            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();

            $table->string('ip', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('request_id', 64)->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['actor_type', 'actor_id']);
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('energy_consumptions');
        Schema::dropIfExists('energy_tariffs');
    }
};
