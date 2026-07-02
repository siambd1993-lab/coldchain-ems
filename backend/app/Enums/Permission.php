<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Fine-grained permissions, expressed as `module.action` slugs.
 *
 * Permissions are the atomic unit of authorization. Roles (see {@see Role})
 * are bundles of permissions; middleware and policies always check a concrete
 * Permission, never a Role, so the role→permission mapping can evolve without
 * touching call sites.
 *
 * Naming: `<module>.<action>`. Keep actions consistent across modules
 * (view / create / update / delete / manage) so the matrix stays predictable.
 */
enum Permission: string
{
    // ── Platform / tenancy administration ────────────────────────────────
    case TenantsView          = 'tenants.view';
    case TenantsManage        = 'tenants.manage';
    case BranchesView         = 'branches.view';
    case BranchesCreate       = 'branches.create';
    case BranchesUpdate       = 'branches.update';
    case BranchesDelete       = 'branches.delete';

    // ── Identity & access ────────────────────────────────────────────────
    case UsersView            = 'users.view';
    case UsersCreate          = 'users.create';
    case UsersUpdate          = 'users.update';
    case UsersDelete          = 'users.delete';
    case UsersAssignRoles     = 'users.assign_roles';
    case RolesView            = 'roles.view';
    case RolesManage          = 'roles.manage';

    // ── Customers (depositors renting cold-storage space) ────────────────
    case CustomersView        = 'customers.view';
    case CustomersCreate      = 'customers.create';
    case CustomersUpdate      = 'customers.update';
    case CustomersDelete      = 'customers.delete';

    // ── Cold-storage facility structure ──────────────────────────────────
    case ChambersView         = 'chambers.view';
    case ChambersCreate       = 'chambers.create';
    case ChambersUpdate       = 'chambers.update';
    case ChambersDelete       = 'chambers.delete';
    case StorageUnitsView     = 'storage_units.view';
    case StorageUnitsManage   = 'storage_units.manage';

    // ── Inventory movements ──────────────────────────────────────────────
    case StockView            = 'stock.view';
    case StockIn              = 'stock.stock_in';
    case StockOut             = 'stock.stock_out';
    case StockTransfer        = 'stock.transfer';
    case StockAdjust          = 'stock.adjust';

    // ── Billing & receivables ────────────────────────────────────────────
    case BillingView          = 'billing.view';
    case BillingManage        = 'billing.manage';
    case InvoiceGenerate      = 'billing.generate_invoice';
    case InvoiceVoid          = 'billing.void_invoice';
    case PaymentsView         = 'payments.view';
    case PaymentsRecord       = 'payments.record';
    case PaymentsRefund       = 'payments.refund';

    // ── IoT devices & telemetry ──────────────────────────────────────────
    case DevicesView          = 'devices.view';
    case DevicesManage        = 'devices.manage';
    case DevicesProvision     = 'devices.provision';
    case TelemetryView        = 'telemetry.view';

    // ── Energy management ────────────────────────────────────────────────
    case EnergyView           = 'energy.view';
    case EnergyManage         = 'energy.manage';

    // ── Alerts & incidents ───────────────────────────────────────────────
    case AlertsView           = 'alerts.view';
    case AlertsAcknowledge    = 'alerts.acknowledge';
    case AlertsManage         = 'alerts.manage';

    // ── Reporting & analytics ────────────────────────────────────────────
    case ReportsView          = 'reports.view';
    case ReportsExport        = 'reports.export';

    // ── Compliance ───────────────────────────────────────────────────────
    case AuditView            = 'audit.view';

    // ── Tenant settings ──────────────────────────────────────────────────
    case SettingsView         = 'settings.view';
    case SettingsManage       = 'settings.manage';

    /**
     * All permission slugs — handy for the platform-admin wildcard and for
     * validating a stored role definition.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $p): string => $p->value, self::cases());
    }

    /**
     * The coarse module a permission belongs to (text before the first dot).
     * Used to group permissions in the admin UI.
     */
    public function module(): string
    {
        return explode('.', $this->value, 2)[0];
    }

    /** Human-readable label for admin screens. */
    public function label(): string
    {
        return ucwords(str_replace(['.', '_'], ' ', $this->value));
    }
}
