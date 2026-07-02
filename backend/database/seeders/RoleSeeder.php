<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Role as RoleEnum;
use App\Models\Role;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Upserts system roles into the `roles` table.
 *
 * Strategy:
 *   - platform_admin  → tenant_id = null  (one global record)
 *   - all other roles → one record per tenant, seeded for every tenant
 *                       that currently exists in the DB.
 *
 * Safe to run multiple times (upsert on tenant_id + slug).
 * Called from DemoSeeder after the demo tenant is created, and should
 * be re-run via `php artisan db:seed --class=RoleSeeder` whenever a new
 * tenant is provisioned in production.
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $this->seedPlatformAdmin();
            $this->seedTenantRoles();
        });
    }

    // ─────────────────────────────────────────────────────────────────────

    /** Seeds (or refreshes) roles for a specific tenant only. */
    public function runForTenant(Tenant $tenant): void
    {
        DB::transaction(function () use ($tenant): void {
            $this->upsertRolesForTenant($tenant->id);
        });
    }

    // ─────────────────────────────────────────────────────────────────────

    private function seedPlatformAdmin(): void
    {
        $role = RoleEnum::PlatformAdmin;

        Role::updateOrCreate(
            ['tenant_id' => null, 'slug' => $role->value],
            [
                'name'        => $role->label(),
                'description' => 'Unrestricted platform-level administrator. Not assignable to tenant users.',
                'permissions' => $role->permissionValues(),
                'is_system'   => true,
            ],
        );
    }

    private function seedTenantRoles(): void
    {
        Tenant::withTrashed()->each(function (Tenant $tenant): void {
            $this->upsertRolesForTenant($tenant->id);
        });
    }

    private function upsertRolesForTenant(int $tenantId): void
    {
        foreach (RoleEnum::tenantAssignable() as $role) {
            Role::updateOrCreate(
                ['tenant_id' => $tenantId, 'slug' => $role->value],
                [
                    'name'        => $role->label(),
                    'description' => $this->descriptionFor($role),
                    'permissions' => $role->permissionValues(),
                    'is_system'   => true,
                ],
            );
        }
    }

    private function descriptionFor(RoleEnum $role): string
    {
        return match ($role) {
            RoleEnum::TenantOwner   => 'Full access to all tenant resources. Cannot be customised.',
            RoleEnum::TenantAdmin   => 'Manages users, branches and all operational modules.',
            RoleEnum::BranchManager => 'Day-to-day operations and billing within their branch.',
            RoleEnum::Operator      => 'Records stock intake and release; no billing access.',
            RoleEnum::Accountant    => 'Full billing and payment access; limited operational view.',
            RoleEnum::Technician    => 'IoT device management, energy monitoring, and alerts.',
            RoleEnum::Auditor       => 'Read-only access across all modules for compliance review.',
            default                 => '',
        };
    }
}
