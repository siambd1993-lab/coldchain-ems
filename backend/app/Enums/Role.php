<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Permission as P;

/**
 * System roles. A role is a named bundle of {@see Permission}s.
 *
 * The mapping here is the *default* baseline shipped with every tenant. Tenants
 * may later define custom roles in the database (see the `roles` /
 * `role_permission` tables); those are resolved at runtime and merged on top of
 * these defaults. Keeping the baseline in code guarantees a sane starting point
 * and lets tests assert against a known matrix.
 *
 * `PlatformAdmin` is special: it is scoped to the platform operator (us), not a
 * tenant, and bypasses both tenant row-scoping and per-permission checks. It is
 * never assignable through the tenant admin UI.
 */
enum Role: string
{
    case PlatformAdmin = 'platform_admin';
    case TenantOwner   = 'tenant_owner';
    case TenantAdmin   = 'tenant_admin';
    case BranchManager = 'branch_manager';
    case Operator      = 'operator';
    case Accountant    = 'accountant';
    case Technician    = 'technician';
    case Auditor       = 'auditor';

    /** Human-readable label for admin screens. */
    public function label(): string
    {
        return match ($this) {
            self::PlatformAdmin => 'Platform Administrator',
            self::TenantOwner   => 'Owner',
            self::TenantAdmin   => 'Administrator',
            self::BranchManager => 'Branch Manager',
            self::Operator      => 'Operator',
            self::Accountant    => 'Accountant',
            self::Technician    => 'Technician',
            self::Auditor       => 'Auditor',
        };
    }

    /**
     * Roles that platform staff may assign vs. those a tenant can self-assign.
     * PlatformAdmin is intentionally excluded from tenant-assignable roles.
     *
     * @return list<self>
     */
    public static function tenantAssignable(): array
    {
        return [
            self::TenantOwner,
            self::TenantAdmin,
            self::BranchManager,
            self::Operator,
            self::Accountant,
            self::Technician,
            self::Auditor,
        ];
    }

    /**
     * True when this role sees every tenant and skips per-permission gating.
     */
    public function isSuperAdmin(): bool
    {
        return $this === self::PlatformAdmin;
    }

    /**
     * The baseline permission set granted to this role.
     *
     * @return list<Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            // Wildcard — every declared permission.
            self::PlatformAdmin => Permission::cases(),

            self::TenantOwner => [
                P::TenantsView, P::SettingsView, P::SettingsManage,
                P::BranchesView, P::BranchesCreate, P::BranchesUpdate, P::BranchesDelete,
                P::UsersView, P::UsersCreate, P::UsersUpdate, P::UsersDelete, P::UsersAssignRoles,
                P::RolesView, P::RolesManage,
                P::CustomersView, P::CustomersCreate, P::CustomersUpdate, P::CustomersDelete,
                P::ChambersView, P::ChambersCreate, P::ChambersUpdate, P::ChambersDelete,
                P::StorageUnitsView, P::StorageUnitsManage,
                P::StockView, P::StockIn, P::StockOut, P::StockTransfer, P::StockAdjust,
                P::BillingView, P::BillingManage, P::InvoiceGenerate, P::InvoiceVoid,
                P::PaymentsView, P::PaymentsRecord, P::PaymentsRefund,
                P::DevicesView, P::DevicesManage, P::DevicesProvision, P::TelemetryView,
                P::EnergyView, P::EnergyManage,
                P::AlertsView, P::AlertsAcknowledge, P::AlertsManage,
                P::ReportsView, P::ReportsExport,
                P::AuditView,
            ],

            self::TenantAdmin => [
                P::SettingsView,
                P::BranchesView, P::BranchesCreate, P::BranchesUpdate,
                P::UsersView, P::UsersCreate, P::UsersUpdate, P::UsersAssignRoles,
                P::RolesView,
                P::CustomersView, P::CustomersCreate, P::CustomersUpdate, P::CustomersDelete,
                P::ChambersView, P::ChambersCreate, P::ChambersUpdate,
                P::StorageUnitsView, P::StorageUnitsManage,
                P::StockView, P::StockIn, P::StockOut, P::StockTransfer, P::StockAdjust,
                P::BillingView, P::BillingManage, P::InvoiceGenerate,
                P::PaymentsView, P::PaymentsRecord,
                P::DevicesView, P::DevicesManage, P::TelemetryView,
                P::EnergyView, P::EnergyManage,
                P::AlertsView, P::AlertsAcknowledge, P::AlertsManage,
                P::ReportsView, P::ReportsExport,
                P::AuditView,
            ],

            self::BranchManager => [
                P::BranchesView,
                P::UsersView,
                P::CustomersView, P::CustomersCreate, P::CustomersUpdate,
                P::ChambersView,
                P::StorageUnitsView, P::StorageUnitsManage,
                P::StockView, P::StockIn, P::StockOut, P::StockTransfer, P::StockAdjust,
                P::BillingView, P::InvoiceGenerate,
                P::PaymentsView, P::PaymentsRecord,
                P::DevicesView, P::TelemetryView,
                P::EnergyView,
                P::AlertsView, P::AlertsAcknowledge,
                P::ReportsView, P::ReportsExport,
            ],

            self::Operator => [
                P::CustomersView,
                P::ChambersView,
                P::StorageUnitsView,
                P::StockView, P::StockIn, P::StockOut, P::StockTransfer,
                P::TelemetryView,
                P::AlertsView, P::AlertsAcknowledge,
            ],

            self::Accountant => [
                P::CustomersView,
                P::StockView,
                P::BillingView, P::BillingManage, P::InvoiceGenerate, P::InvoiceVoid,
                P::PaymentsView, P::PaymentsRecord, P::PaymentsRefund,
                P::ReportsView, P::ReportsExport,
            ],

            self::Technician => [
                P::ChambersView,
                P::StorageUnitsView,
                P::DevicesView, P::DevicesManage, P::DevicesProvision, P::TelemetryView,
                P::EnergyView, P::EnergyManage,
                P::AlertsView, P::AlertsAcknowledge, P::AlertsManage,
            ],

            self::Auditor => [
                P::TenantsView, P::SettingsView, P::BranchesView,
                P::UsersView, P::RolesView,
                P::CustomersView,
                P::ChambersView, P::StorageUnitsView,
                P::StockView,
                P::BillingView, P::PaymentsView,
                P::DevicesView, P::TelemetryView,
                P::EnergyView,
                P::AlertsView,
                P::ReportsView, P::ReportsExport,
                P::AuditView,
            ],
        };
    }

    /**
     * The role's permissions as plain slugs — the shape stored in the JWT and
     * compared by middleware.
     *
     * @return list<string>
     */
    public function permissionValues(): array
    {
        return array_map(static fn (Permission $p): string => $p->value, $this->permissions());
    }
}
