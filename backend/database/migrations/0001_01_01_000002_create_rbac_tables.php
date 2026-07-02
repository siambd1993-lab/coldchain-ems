<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Role-based access control + auth token storage.
 *
 *  - roles:        named permission bundles. System roles (seeded from the
 *                  Role enum) carry is_system=true; tenants may add custom ones.
 *  - role_user:    which roles a user holds (the "what can you do").
 *  - branch_user:  which branches a user may act in (the "where"); an empty set
 *                  means all branches within the tenant.
 *  - refresh_tokens: opaque, rotating, server-side refresh tokens with reuse
 *                  detection via a family id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            // Null tenant = global/system role (e.g. platform_admin).
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            $table->string('slug');
            $table->string('name');
            $table->string('description')->nullable();
            $table->json('permissions'); // effective permission slugs
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
        });

        Schema::create('role_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'role_id']);
        });

        Schema::create('branch_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'branch_id']);
        });

        Schema::create('refresh_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();

            $table->string('token_hash', 64)->unique(); // sha256 hex of the opaque secret
            $table->uuid('family_id')->index();          // rotation family for reuse detection
            $table->foreignId('previous_id')->nullable()->constrained('refresh_tokens')->nullOnDelete();

            $table->string('user_agent')->nullable();
            $table->string('ip', 45)->nullable();

            $table->timestamp('expires_at');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoked_reason')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refresh_tokens');
        Schema::dropIfExists('branch_user');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('roles');
    }
};
