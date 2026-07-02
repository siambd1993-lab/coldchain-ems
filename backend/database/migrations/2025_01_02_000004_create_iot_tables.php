<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * IoT ingestion & monitoring:
 *   devices           → physical hardware (sensors, gateways, PLC, BMS, meters).
 *   device_channels   → individual measured signals a device exposes.
 *   telemetry_readings→ raw time-series (high volume; partition by time in prod).
 *   telemetry_rollups → pre-aggregated buckets for dashboards & trends.
 *   alerts            → threshold breaches / device faults with an ack workflow.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('chamber_id')->nullable()->constrained('chambers')->nullOnDelete();

            $table->string('device_uid'); // hardware identifier, unique per tenant
            $table->string('name');
            $table->enum('device_type', [
                'sensor', 'gateway', 'plc', 'bms', 'inverter', 'energy_meter', 'controller',
            ])->default('sensor');
            $table->enum('protocol', [
                'mqtt', 'modbus_tcp', 'modbus_rtu', 'rs485', 'http', 'snmp',
            ])->nullable();
            $table->enum('status', ['provisioning', 'online', 'offline', 'fault', 'decommissioned'])
                ->default('provisioning');

            $table->string('model')->nullable();
            $table->string('manufacturer')->nullable();
            $table->string('firmware_version')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('mac_address', 17)->nullable();

            // Device credential for MQTT/HTTP ingest — store only the hash.
            $table->string('auth_token_hash', 64)->nullable();

            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('last_heartbeat_at')->nullable();

            $table->json('config')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'device_uid']);
            $table->index(['tenant_id', 'branch_id', 'status']);
            $table->index('chamber_id');
        });

        Schema::create('device_channels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();
            $table->foreignId('chamber_id')->nullable()->constrained('chambers')->nullOnDelete();

            $table->string('channel_key'); // e.g. "temp_1", "kwh_total"
            $table->enum('metric', [
                'temperature', 'humidity', 'power_kw', 'energy_kwh', 'voltage', 'current',
                'door_state', 'compressor_state', 'pressure', 'co2', 'setpoint',
            ]);
            $table->string('unit', 16)->nullable();
            $table->string('label')->nullable();

            // Per-channel alert bounds (fallback to chamber targets when null).
            $table->decimal('min_threshold', 12, 4)->nullable();
            $table->decimal('max_threshold', 12, 4)->nullable();

            $table->decimal('last_value', 14, 4)->nullable();
            $table->timestamp('last_value_at')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(['device_id', 'channel_key']);
            $table->index(['tenant_id', 'metric']);
        });

        Schema::create('telemetry_readings', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();
            $table->foreignId('channel_id')->nullable()->constrained('device_channels')->nullOnDelete();
            $table->foreignId('chamber_id')->nullable()->constrained('chambers')->nullOnDelete();

            $table->string('metric', 32);
            $table->decimal('value', 14, 4);
            $table->string('unit', 16)->nullable();
            $table->enum('quality', ['good', 'suspect', 'bad'])->default('good');

            $table->timestamp('recorded_at');            // device-reported time
            $table->timestamp('ingested_at')->useCurrent(); // server receive time

            $table->index(['tenant_id', 'device_id', 'recorded_at']);
            $table->index(['chamber_id', 'metric', 'recorded_at']);
            $table->index(['channel_id', 'recorded_at']);
        });

        Schema::create('telemetry_rollups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('chamber_id')->nullable()->constrained('chambers')->nullOnDelete();
            $table->foreignId('device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->foreignId('channel_id')->nullable()->constrained('device_channels')->nullOnDelete();

            $table->string('metric', 32);
            $table->enum('window', ['1m', '5m', '1h', '1d']);
            $table->timestamp('bucket_start');

            $table->unsignedInteger('sample_count')->default(0);
            $table->decimal('min_value', 14, 4)->nullable();
            $table->decimal('max_value', 14, 4)->nullable();
            $table->decimal('avg_value', 14, 4)->nullable();
            $table->decimal('sum_value', 18, 4)->nullable();
            $table->string('unit', 16)->nullable();

            $table->timestamps();

            $table->unique(['channel_id', 'window', 'bucket_start'], 'rollup_channel_window_bucket_unique');
            $table->index(['tenant_id', 'chamber_id', 'metric', 'window', 'bucket_start'], 'rollup_lookup_index');
        });

        Schema::create('alerts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('chamber_id')->nullable()->constrained('chambers')->nullOnDelete();
            $table->foreignId('device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->foreignId('channel_id')->nullable()->constrained('device_channels')->nullOnDelete();

            $table->enum('alert_type', [
                'temperature_high', 'temperature_low', 'humidity_high', 'humidity_low',
                'power_failure', 'door_open', 'device_offline', 'compressor_fault',
                'sensor_fault', 'energy_spike', 'threshold_breach',
            ]);
            $table->enum('severity', ['info', 'warning', 'critical', 'emergency'])->default('warning');
            $table->enum('status', ['active', 'acknowledged', 'resolved', 'suppressed'])->default('active');

            $table->string('title');
            $table->text('message')->nullable();
            $table->string('metric', 32)->nullable();
            $table->decimal('threshold_value', 14, 4)->nullable();
            $table->decimal('observed_value', 14, 4)->nullable();

            $table->timestamp('triggered_at');
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('resolution_note')->nullable();

            // Deduplication key so a persisting condition doesn't spam new rows.
            $table->string('dedupe_key')->nullable();
            $table->json('context')->nullable();
            $table->json('notified_channels')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'branch_id', 'status', 'severity']);
            $table->index(['chamber_id', 'status']);
            $table->index(['dedupe_key', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
        Schema::dropIfExists('telemetry_rollups');
        Schema::dropIfExists('telemetry_readings');
        Schema::dropIfExists('device_channels');
        Schema::dropIfExists('devices');
    }
};
