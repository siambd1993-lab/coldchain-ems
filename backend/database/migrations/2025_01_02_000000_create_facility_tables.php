<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Facility structure inside a branch:
 *   chamber  → a temperature-controlled room (freezer, chiller, ripening…)
 *   storage_unit → a rentable subdivision of a chamber (rack, pallet position,
 *                  bin, or demarcated floor space).
 *
 * Denormalised "current_*" and "occupied_*" columns are maintained by the
 * telemetry and inventory pipelines for fast dashboard reads; they are caches,
 * not sources of truth.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chambers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();

            $table->string('code');
            $table->string('name');
            $table->enum('chamber_type', [
                'freezer', 'chiller', 'cold_room', 'blast_freezer', 'ripening', 'ambient',
            ])->default('cold_room');
            $table->enum('status', ['operational', 'maintenance', 'offline', 'defrost'])
                ->default('operational');

            // Capacity envelope.
            $table->decimal('capacity_weight_kg', 14, 3)->nullable();
            $table->decimal('capacity_volume_m3', 12, 3)->nullable();
            $table->unsignedInteger('capacity_slots')->nullable();
            $table->decimal('area_sqft', 12, 2)->nullable();

            // Set-point band the chamber must hold.
            $table->decimal('target_temp_min_c', 5, 2)->nullable();
            $table->decimal('target_temp_max_c', 5, 2)->nullable();
            $table->decimal('target_humidity_min', 5, 2)->nullable();
            $table->decimal('target_humidity_max', 5, 2)->nullable();

            // Latest observed readings (denormalised from telemetry).
            $table->decimal('current_temp_c', 5, 2)->nullable();
            $table->decimal('current_humidity', 5, 2)->nullable();
            $table->timestamp('readings_updated_at')->nullable();

            $table->string('floor')->nullable();
            $table->string('zone')->nullable();
            $table->json('settings')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['branch_id', 'code']);
            $table->index(['tenant_id', 'branch_id', 'status']);
        });

        Schema::create('storage_units', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('chamber_id')->constrained('chambers')->cascadeOnDelete();

            $table->string('code');
            $table->string('label')->nullable();
            $table->enum('unit_type', [
                'rack', 'shelf', 'pallet_position', 'bin', 'floor_space', 'room',
            ])->default('pallet_position');
            $table->enum('status', ['available', 'occupied', 'reserved', 'maintenance'])
                ->default('available');

            $table->decimal('capacity_weight_kg', 14, 3)->nullable();
            $table->decimal('capacity_volume_m3', 12, 3)->nullable();
            $table->decimal('occupied_weight_kg', 14, 3)->default(0);

            // Physical coordinates within the chamber (optional).
            $table->string('grid_row')->nullable();
            $table->string('grid_column')->nullable();
            $table->string('level')->nullable();

            $table->json('settings')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['chamber_id', 'code']);
            $table->index(['tenant_id', 'branch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storage_units');
        Schema::dropIfExists('chambers');
    }
};
