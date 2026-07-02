<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Identity & tenancy foundation: tenants, branches, users.
 *
 * A tenant is a cold-storage operator (our paying customer). A branch is a
 * physical facility owned by that tenant. Users belong to a tenant (except
 * platform staff, whose tenant_id is null) and optionally to a home branch.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('legal_name')->nullable();
            $table->string('domain')->nullable()->unique(); // custom/sub domain for tenant resolution

            $table->enum('status', ['trial', 'active', 'past_due', 'suspended', 'cancelled'])
                ->default('trial');
            $table->string('plan')->default('starter');

            $table->string('timezone')->default('Asia/Dhaka');
            $table->char('currency', 3)->default('BDT');
            $table->string('locale', 8)->default('en');

            // Feature flags / branding / limits.
            $table->json('settings')->nullable();
            // Per-tenant integration secrets (bKash/Nagad/SMS) — encrypted at rest.
            $table->text('integration_credentials')->nullable();

            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();

            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('subscription_ends_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });

        Schema::create('branches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();

            $table->string('code'); // human code, unique within tenant
            $table->string('name');
            $table->enum('status', ['active', 'inactive', 'under_maintenance'])->default('active');

            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city')->nullable();
            $table->string('district')->nullable();
            $table->string('division')->nullable();
            $table->string('postal_code', 16)->nullable();
            $table->char('country', 2)->default('BD');

            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('timezone')->nullable(); // inherits tenant when null

            $table->json('settings')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            // Nullable: platform-admin accounts are not bound to a tenant.
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();

            $table->string('name');
            $table->string('email')->unique(); // login identifier — globally unique
            $table->string('phone')->nullable();
            $table->string('password');

            $table->enum('status', ['invited', 'active', 'suspended'])->default('active');
            $table->boolean('is_platform_admin')->default(false);

            $table->string('avatar_path')->nullable();
            $table->string('locale', 8)->nullable();
            $table->string('timezone')->nullable();

            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();

            // Two-factor (TOTP) — secret & recovery codes encrypted in the model.
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();

            $table->json('settings')->nullable();

            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
            $table->index('branch_id');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
        Schema::dropIfExists('branches');
        Schema::dropIfExists('tenants');
    }
};
