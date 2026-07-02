<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AlertController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Billing\InvoiceController;
use App\Http\Controllers\Api\Billing\PaymentController;
use App\Http\Controllers\Api\Billing\RatePlanController;
use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\ChamberController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\EnergyController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\StockController;
use App\Http\Controllers\Api\StorageUnitController;
use App\Http\Controllers\Api\UserController;
use App\Models\InvoiceLine;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes  (automatically prefixed with /api by the bootstrap router)
|--------------------------------------------------------------------------
|
| All routes are versioned under /v1.
|
| Three trust tiers:
|
|   1. public      — auth front door (tight per-email/IP throttle)
|   2. authenticated — token valid, tenant context booted but tenant optional
|   3. tenant-scoped — active tenant is mandatory; all business modules here
|
| Within the tenant tier every route carries a `permission:` middleware that
| produces a 403 at the routing layer before the controller is reached.
|
*/

Route::prefix('v1')->group(function (): void {

    // ────────────────────────────────────────────────────────────────────
    // 1.  Public — authentication front door
    // ────────────────────────────────────────────────────────────────────

    Route::middleware('throttle:auth')->group(function (): void {
        Route::post('auth/login', [AuthController::class, 'login']);
        Route::post('auth/refresh', [AuthController::class, 'refresh']);
    });

    // ────────────────────────────────────────────────────────────────────
    // 2.  Authenticated — token required, tenant optional
    //     (platform admins have no tenant but still need /me and /logout)
    // ────────────────────────────────────────────────────────────────────

    Route::middleware(['auth:api', 'tenant.resolve', 'throttle:api'])->group(function (): void {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);
    });

    // ────────────────────────────────────────────────────────────────────
    // 3.  Tenant-scoped business modules
    // ────────────────────────────────────────────────────────────────────

    Route::middleware(['auth:api', 'tenant.resolve', 'tenant', 'throttle:api'])
        ->group(function (): void {

            // ── Branches (physical facilities) ───────────────────────────
            Route::middleware('permission:branches.view')->group(function (): void {
                Route::get('branches', [BranchController::class, 'index']);
                Route::get('branches/{branch}', [BranchController::class, 'show']);
            });
            Route::post('branches', [BranchController::class, 'store'])
                ->middleware('permission:branches.create');
            Route::put('branches/{branch}', [BranchController::class, 'update'])
                ->middleware('permission:branches.update');
            Route::delete('branches/{branch}', [BranchController::class, 'destroy'])
                ->middleware('permission:branches.delete');

            // ── Products (commodity catalogue) ───────────────────────────
            Route::middleware('permission:stock.view')->group(function (): void {
                Route::get('products', [ProductController::class, 'index']);
                Route::get('products/{product}', [ProductController::class, 'show']);
            });
            Route::post('products', [ProductController::class, 'store'])
                ->middleware('permission:stock.stock_in');
            Route::put('products/{product}', [ProductController::class, 'update'])
                ->middleware('permission:stock.stock_in');
            Route::delete('products/{product}', [ProductController::class, 'destroy'])
                ->middleware('permission:stock.adjust');

            // ── Customers (depositors) ───────────────────────────────────
            Route::middleware('permission:customers.view')->group(function (): void {
                Route::get('customers', [CustomerController::class, 'index']);
                Route::get('customers/{customer}', [CustomerController::class, 'show']);
            });
            Route::post('customers', [CustomerController::class, 'store'])
                ->middleware('permission:customers.create');
            Route::put('customers/{customer}', [CustomerController::class, 'update'])
                ->middleware('permission:customers.update');
            Route::delete('customers/{customer}', [CustomerController::class, 'destroy'])
                ->middleware('permission:customers.delete');

            // ── Chambers ─────────────────────────────────────────────────
            Route::middleware('permission:chambers.view')->group(function (): void {
                Route::get('chambers', [ChamberController::class, 'index']);
                Route::get('chambers/{chamber}', [ChamberController::class, 'show']);
            });
            Route::post('chambers', [ChamberController::class, 'store'])
                ->middleware('permission:chambers.create');
            Route::put('chambers/{chamber}', [ChamberController::class, 'update'])
                ->middleware('permission:chambers.update');
            Route::delete('chambers/{chamber}', [ChamberController::class, 'destroy'])
                ->middleware('permission:chambers.delete');

            // ── Storage Units ────────────────────────────────────────────
            Route::middleware('permission:storage_units.view')->group(function (): void {
                Route::get('storage-units', [StorageUnitController::class, 'index']);
                Route::get('storage-units/{storage_unit}', [StorageUnitController::class, 'show']);
            });
            Route::middleware('permission:storage_units.manage')->group(function (): void {
                Route::post('storage-units', [StorageUnitController::class, 'store']);
                Route::put('storage-units/{storage_unit}', [StorageUnitController::class, 'update']);
                Route::delete('storage-units/{storage_unit}', [StorageUnitController::class, 'destroy']);
            });

            // ── Inventory: lots & movements ──────────────────────────────
            Route::middleware('permission:stock.view')->group(function (): void {
                Route::get('stock-lots', [StockController::class, 'index']);
                Route::get('stock-lots/{lot}', [StockController::class, 'show']);
                Route::get('stock-lots/{lot}/movements', [StockController::class, 'movements']);
            });
            Route::post('stock-lots', [StockController::class, 'intake'])
                ->middleware('permission:stock.stock_in');
            Route::post('stock-lots/{lot}/release', [StockController::class, 'release'])
                ->middleware('permission:stock.stock_out');
            Route::post('stock-lots/{lot}/adjust', [StockController::class, 'adjust'])
                ->middleware('permission:stock.adjust');
            Route::post('stock-lots/{lot}/transfer', [StockController::class, 'transfer'])
                ->middleware('permission:stock.transfer');

            // ── Rate Plans ───────────────────────────────────────────────
            Route::middleware('permission:billing.view')->group(function (): void {
                Route::get('rate-plans', [RatePlanController::class, 'index']);
                Route::get('rate-plans/{rate_plan}', [RatePlanController::class, 'show']);
            });
            Route::middleware('permission:billing.manage')->group(function (): void {
                Route::post('rate-plans', [RatePlanController::class, 'store']);
                Route::put('rate-plans/{rate_plan}', [RatePlanController::class, 'update']);
                Route::delete('rate-plans/{rate_plan}', [RatePlanController::class, 'destroy']);
            });

            // ── Invoices ─────────────────────────────────────────────────
            Route::middleware('permission:billing.view')->group(function (): void {
                Route::get('invoices', [InvoiceController::class, 'index']);
                Route::get('invoices/{invoice}', [InvoiceController::class, 'show']);
            });
            Route::middleware('permission:billing.manage')->group(function (): void {
                Route::post('invoices', [InvoiceController::class, 'store']);
                Route::put('invoices/{invoice}', [InvoiceController::class, 'update']);
                Route::delete('invoices/{invoice}', [InvoiceController::class, 'destroy']);
                // Line management (only on draft invoices).
                Route::post('invoices/{invoice}/lines', [InvoiceController::class, 'storeLine']);
                Route::put('invoices/{invoice}/lines/{line}', [InvoiceController::class, 'updateLine']);
                Route::delete('invoices/{invoice}/lines/{line}', [InvoiceController::class, 'destroyLine']);
            });
            // Status transitions carry their own higher-intent permissions.
            Route::post('invoices/{invoice}/issue', [InvoiceController::class, 'issue'])
                ->middleware('permission:billing.generate_invoice');
            Route::post('invoices/{invoice}/void', [InvoiceController::class, 'void'])
                ->middleware('permission:billing.void_invoice');

            // ── Payments ─────────────────────────────────────────────────
            Route::middleware('permission:payments.view')->group(function (): void {
                Route::get('payments', [PaymentController::class, 'index']);
                Route::get('payments/{payment}', [PaymentController::class, 'show']);
            });
            Route::post('payments', [PaymentController::class, 'store'])
                ->middleware('permission:payments.record');
            Route::post('payments/{payment}/allocate', [PaymentController::class, 'allocate'])
                ->middleware('permission:payments.record');

            // ── Team: staff accounts ─────────────────────────────────────
            Route::middleware('permission:users.view')->group(function (): void {
                Route::get('users', [UserController::class, 'index']);
                Route::get('users/{user}', [UserController::class, 'show']);
            });
            Route::post('users', [UserController::class, 'store'])
                ->middleware('permission:users.create');
            Route::put('users/{user}', [UserController::class, 'update'])
                ->middleware('permission:users.update');
            Route::delete('users/{user}', [UserController::class, 'destroy'])
                ->middleware('permission:users.delete');
            Route::put('users/{user}/roles', [UserController::class, 'syncRoles'])
                ->middleware('permission:users.assign_roles');

            // ── Team: roles & the permission catalog ─────────────────────
            Route::middleware('permission:roles.view')->group(function (): void {
                Route::get('roles', [RoleController::class, 'index']);
                Route::get('permissions', [RoleController::class, 'permissions']);
            });
            Route::middleware('permission:roles.manage')->group(function (): void {
                Route::post('roles', [RoleController::class, 'store']);
                Route::put('roles/{role}', [RoleController::class, 'update']);
                Route::delete('roles/{role}', [RoleController::class, 'destroy']);
            });

            // ── Reports & compliance ─────────────────────────────────────
            Route::middleware('permission:reports.view')->group(function (): void {
                Route::get('reports/occupancy', [ReportController::class, 'occupancy']);
                Route::get('reports/revenue', [ReportController::class, 'revenue']);
                Route::get('reports/receivables', [ReportController::class, 'receivables']);
                Route::get('reports/stock', [ReportController::class, 'stock']);
            });
            Route::get('audit-logs', [AuditLogController::class, 'index'])
                ->middleware('permission:audit.view');

            // ── IoT: devices, alerts, energy ─────────────────────────────
            Route::middleware('permission:devices.view')->group(function (): void {
                Route::get('devices', [DeviceController::class, 'index']);
                Route::get('devices/{device}', [DeviceController::class, 'show']);
            });
            Route::middleware('permission:devices.manage')->group(function (): void {
                Route::post('devices', [DeviceController::class, 'store']);
                Route::put('devices/{device}', [DeviceController::class, 'update']);
                Route::delete('devices/{device}', [DeviceController::class, 'destroy']);
            });

            Route::get('alerts', [AlertController::class, 'index'])
                ->middleware('permission:alerts.view');
            Route::post('alerts/{alert}/acknowledge', [AlertController::class, 'acknowledge'])
                ->middleware('permission:alerts.acknowledge');
            Route::post('alerts/{alert}/resolve', [AlertController::class, 'resolve'])
                ->middleware('permission:alerts.acknowledge');

            Route::get('energy/summary', [EnergyController::class, 'summary'])
                ->middleware('permission:energy.view');

        }); // end tenant-scoped group

}); // end v1 prefix
