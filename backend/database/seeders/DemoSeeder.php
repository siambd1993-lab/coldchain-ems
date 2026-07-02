<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Role as RoleEnum;
use App\Models\Branch;
use App\Models\Chamber;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\RatePlan;
use App\Models\Role;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Models\StorageUnit;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds a realistic demo tenant with two branches, multiple users, customers,
 * chambers, stock lots, invoices, and payments. Idempotent via firstOrCreate.
 *
 * Credentials after seeding:
 *   Platform admin : admin@coldchain.test   / password
 *   Owner          : owner@arcturus.test     / password
 *   Admin          : admin@arcturus.test     / password
 *   Branch manager : manager@arcturus.test   / password
 *   Operator       : operator@arcturus.test  / password
 *   Accountant     : accountant@arcturus.test/ password
 *   Technician     : tech@arcturus.test      / password
 *   Auditor        : auditor@arcturus.test   / password
 */
class DemoSeeder extends Seeder
{
    /**
     * Password for every seeded account. Defaults to "password" for local
     * dev; MUST be overridden via DEMO_PASSWORD when seeding anything
     * reachable from the internet.
     */
    private function defaultPassword(): string
    {
        return (string) env('DEMO_PASSWORD', 'password');
    }

    public function run(): void
    {
        DB::transaction(function (): void {
            $platformAdmin = $this->seedPlatformAdmin();
            $tenant        = $this->seedTenant();

            // Seed system roles for this tenant before assigning them.
            (new RoleSeeder())->runForTenant($tenant);

            [$branch1, $branch2] = $this->seedBranches($tenant);
            $this->seedUsers($tenant, $branch1);
            $ratePlans       = $this->seedRatePlans($tenant);
            $products        = $this->seedProducts($tenant);
            $customers       = $this->seedCustomers($tenant, $branch1);
            $chambers        = $this->seedChambers($tenant, $branch1);
            $storageUnits    = $this->seedStorageUnits($tenant, $branch1, $chambers);
            $this->seedInventory($tenant, $branch1, $customers, $products, $chambers, $storageUnits, $ratePlans); // $ratePlans is already the flat array
            $this->seedBillingData($tenant, $branch1, $customers);
        });
    }

    // ── Platform admin ──────────────────────────────────────────────────

    private function seedPlatformAdmin(): User
    {
        return User::firstOrCreate(
            ['email' => 'admin@coldchain.test'],
            [
                'name'               => 'Platform Admin',
                'password'           => Hash::make($this->defaultPassword()),
                'tenant_id'          => null,
                'is_platform_admin'  => true,
                'status'             => 'active',
                'email_verified_at'  => now(),
            ],
        );
    }

    // ── Demo tenant ─────────────────────────────────────────────────────

    private function seedTenant(): Tenant
    {
        return Tenant::firstOrCreate(
            ['slug' => 'arcturus-cold'],
            [
                'name'          => 'Arcturus Cold Storage Ltd.',
                'legal_name'    => 'Arcturus Cold Storage Limited',
                'status'        => 'active',
                'plan'          => 'professional',
                'timezone'      => 'Asia/Dhaka',
                'currency'      => 'BDT',
                'locale'        => 'en',
                'contact_name'  => 'Mr. Rahul Ahmed',
                'contact_email' => 'rahul@arcturus.test',
                'contact_phone' => '+8801711000001',
            ],
        );
    }

    // ── Branches ────────────────────────────────────────────────────────

    private function seedBranches(Tenant $tenant): array
    {
        $b1 = Branch::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'DHK-01'],
            [
                'name'          => 'Dhaka Central Branch',
                'status'        => 'active',
                'address_line1' => 'Plot 45, Industrial Area',
                'city'          => 'Dhaka',
                'district'      => 'Dhaka',
                'division'      => 'Dhaka',
                'country'       => 'BD',
                'phone'         => '+8801711000010',
            ],
        );

        $b2 = Branch::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'CTG-01'],
            [
                'name'          => 'Chittagong Port Branch',
                'status'        => 'active',
                'address_line1' => 'Port Zone, Patenga',
                'city'          => 'Chittagong',
                'district'      => 'Chittagong',
                'division'      => 'Chittagong',
                'country'       => 'BD',
                'phone'         => '+8801711000020',
            ],
        );

        return [$b1, $b2];
    }

    // ── Users ───────────────────────────────────────────────────────────

    private function seedUsers(Tenant $tenant, Branch $mainBranch): void
    {
        $staffMap = [
            ['owner@arcturus.test',      'Rahim Owner',      RoleEnum::TenantOwner,   null],
            ['admin@arcturus.test',       'Admin User',       RoleEnum::TenantAdmin,   null],
            ['manager@arcturus.test',     'Branch Manager',   RoleEnum::BranchManager, $mainBranch->id],
            ['operator@arcturus.test',    'Stock Operator',   RoleEnum::Operator,      $mainBranch->id],
            ['accountant@arcturus.test',  'Billing Staff',    RoleEnum::Accountant,    null],
            ['tech@arcturus.test',        'IoT Technician',   RoleEnum::Technician,    null],
            ['auditor@arcturus.test',     'Compliance Audit', RoleEnum::Auditor,       null],
        ];

        foreach ($staffMap as [$email, $name, $roleEnum, $branchId]) {
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'tenant_id'         => $tenant->id,
                    'branch_id'         => $branchId,
                    'name'              => $name,
                    'password'          => Hash::make($this->defaultPassword()),
                    'status'            => 'active',
                    'email_verified_at' => now(),
                ],
            );

            // Assign the system role if not already attached.
            $role = Role::where('tenant_id', $tenant->id)->where('slug', $roleEnum->value)->first();
            if ($role && ! $user->roles()->where('role_id', $role->id)->exists()) {
                $user->roles()->attach($role->id);
            }

            // Restrict branch manager / operator to the main branch.
            if ($branchId !== null && ! $user->branches()->where('branch_id', $branchId)->exists()) {
                $user->branches()->attach($branchId);
            }
        }
    }

    // ── Rate plans ──────────────────────────────────────────────────────

    private function seedRatePlans(Tenant $tenant): array
    {
        $plans = [];

        $defs = [
            ['RP-001', 'Standard Chiller (per kg/month)',   'per_kg_per_month',     200,          0, '15.00'],
            ['RP-002', 'Freezer Premium (per kg/month)',    'per_kg_per_month',     350,          0, '15.00'],
            ['RP-003', 'Bulk Pallet (per pallet/month)',    'per_pallet_per_month', 18_000_00, 5_000_00, '15.00'],
            ['RP-004', 'Cold-Break Daily (per kg/day)',     'per_kg_per_day',       12,          0, '15.00'],
            ['RP-005', 'Pharma Grade (flat monthly)',       'flat_monthly',         50_000_00,   0, '0.00'],
        ];

        foreach ($defs as [$code, $name, $method, $rate, $min, $tax]) {
            $plans[] = RatePlan::firstOrCreate(
                ['tenant_id' => $tenant->id, 'code' => $code],
                [
                    'name'                   => $name,
                    'billing_method'         => $method,
                    'rate_poisha'            => $rate,
                    'minimum_charge_poisha'  => $min,
                    'grace_days'             => 1,
                    'tax_rate'               => $tax,
                    'is_active'              => true,
                ],
            );
        }

        return $plans; // array of 5 RatePlan models (index 0–4)
    }

    // ── Products ────────────────────────────────────────────────────────

    private function seedProducts(Tenant $tenant): array
    {
        $defs = [
            ['PRD-001', 'Fresh Fish',          'fish',       'kg',    -2.0,  2.0,  90],
            ['PRD-002', 'Frozen Shrimp',       'seafood',    'kg',   -20.0,-18.0, 180],
            ['PRD-003', 'Broiler Chicken',     'poultry',    'kg',   -18.0,-12.0,  60],
            ['PRD-004', 'Beef (Halal)',         'meat',       'kg',   -18.0,-12.0, 365],
            ['PRD-005', 'Potato',              'vegetables', 'kg',    4.0,  8.0,  180],
            ['PRD-006', 'Onion',               'vegetables', 'kg',    2.0,  5.0,  120],
            ['PRD-007', 'Mango (Himsagar)',    'fruits',     'crate', 8.0, 12.0,   21],
            ['PRD-008', 'Pharmaceutical Vials','pharma',     'carton',2.0,  8.0,  730],
        ];

        $products = [];
        foreach ($defs as [$code, $name, $cat, $uom, $tmin, $tmax, $shelf]) {
            $products[] = Product::firstOrCreate(
                ['tenant_id' => $tenant->id, 'code' => $code],
                compact('name') + [
                    'category'           => $cat,
                    'unit_of_measure'    => $uom,
                    'default_temp_min_c' => $tmin,
                    'default_temp_max_c' => $tmax,
                    'shelf_life_days'    => $shelf,
                ],
            );
        }

        return $products;
    }

    // ── Customers ───────────────────────────────────────────────────────

    private function seedCustomers(Tenant $tenant, Branch $branch): array
    {
        $defs = [
            ['CUST-0001', 'business',    'Rahim Fisheries Ltd.',     'Md. Rahim',       'rahim@example.test',      '+8801900000001', 500_000_00],
            ['CUST-0002', 'business',    'Karim Agro Export',        'Mr. Karim',       'karim@example.test',      '+8801900000002', 200_000_00],
            ['CUST-0003', 'individual',  'Fatema Begum',             null,              'fatema@example.test',     '+8801900000003', 0],
            ['CUST-0004', 'business',    'Sunrise Pharma Depot',     'Mr. Hossain',     'sunrise@example.test',    '+8801900000004', 1_000_000_00],
            ['CUST-0005', 'business',    'Delta Poultry Processing', 'Ms. Nadia',       'delta@example.test',      '+8801900000005', 300_000_00],
        ];

        $customers = [];
        foreach ($defs as [$code, $type, $name, $contact, $email, $phone, $limit]) {
            $customers[] = Customer::firstOrCreate(
                ['tenant_id' => $tenant->id, 'code' => $code],
                [
                    'branch_id'              => $branch->id,
                    'type'                   => $type,
                    'name'                   => $name,
                    'contact_person'         => $contact,
                    'email'                  => $email,
                    'phone'                  => $phone,
                    'city'                   => 'Dhaka',
                    'district'               => 'Dhaka',
                    'country'                => 'BD',
                    'credit_limit_poisha'    => $limit,
                    'opening_balance_poisha' => 0,
                    'balance_poisha'         => 0,
                    'status'                 => 'active',
                ],
            );
        }

        return $customers;
    }

    // ── Chambers ────────────────────────────────────────────────────────

    private function seedChambers(Tenant $tenant, Branch $branch): array
    {
        $defs = [
            ['CHM-01', 'Freezer Room A',  'freezer',   15_000,  1_500, -25.0, -18.0],
            ['CHM-02', 'Chiller Room B',  'chiller',   20_000,  2_000,   2.0,   8.0],
            ['CHM-03', 'Cold Room C',     'cold_room', 30_000,  3_000,   8.0,  15.0],
            ['CHM-04', 'Blast Freezer',   'blast_freezer', 5_000, 200, -35.0, -28.0],
        ];

        $chambers = [];
        foreach ($defs as [$code, $name, $type, $capKg, $capM3, $tmin, $tmax]) {
            $chambers[] = Chamber::firstOrCreate(
                ['branch_id' => $branch->id, 'code' => $code],
                [
                    'tenant_id'          => $tenant->id,
                    'name'               => $name,
                    'chamber_type'       => $type,
                    'status'             => 'operational',
                    'capacity_weight_kg' => $capKg,
                    'capacity_volume_m3' => $capM3,
                    'capacity_slots'     => (int) ($capKg / 500),
                    'target_temp_min_c'  => $tmin,
                    'target_temp_max_c'  => $tmax,
                    'current_temp_c'     => ($tmin + $tmax) / 2,
                ],
            );
        }

        return $chambers;
    }

    // ── Storage units ────────────────────────────────────────────────────

    private function seedStorageUnits(Tenant $tenant, Branch $branch, array $chambers): array
    {
        // 6 pallet positions per chamber for the demo.
        $units = [];
        foreach ($chambers as $ci => $chamber) {
            for ($i = 1; $i <= 6; $i++) {
                $code = sprintf('%s-P%02d', $chamber->code, $i);
                $units[] = StorageUnit::firstOrCreate(
                    ['chamber_id' => $chamber->id, 'code' => $code],
                    [
                        'tenant_id'          => $tenant->id,
                        'branch_id'          => $branch->id,
                        'label'              => "Pallet {$i} of {$chamber->name}",
                        'unit_type'          => 'pallet_position',
                        'status'             => 'available',
                        'capacity_weight_kg' => 2_500,
                        'capacity_volume_m3' => 20,
                        'occupied_weight_kg' => 0,
                    ],
                );
            }
        }

        return $units;
    }

    // ── Inventory ────────────────────────────────────────────────────────

    private function seedInventory(
        Tenant $tenant,
        Branch $branch,
        array $customers,
        array $products,
        array $chambers,
        array $storageUnits,
        array $ratePlans,
    ): void {
        // Storage unit layout (6 per chamber, seedChambers creates 4 chambers):
        //   chamber[0] (Freezer A)   → $storageUnits[0..5]
        //   chamber[1] (Chiller B)   → $storageUnits[6..11]
        //   chamber[2] (Cold Room C) → $storageUnits[12..17]
        //   chamber[3] (Blast Frzr)  → $storageUnits[18..23]
        //
        // Columns: [customer_idx, product_idx, chamber_idx, unit_idx, rate_plan_idx, qty, days_ago]
        $lots = [
            [0, 0, 1,  6, 0, 5_000, 30],  // Rahim fisheries: fish     → Chiller B, RP Standard
            [0, 1, 0,  0, 1, 2_000, 45],  // Rahim fisheries: shrimp   → Freezer A, RP Freezer
            [1, 2, 0,  1, 1, 3_500, 15],  // Karim agro:      chicken  → Freezer A, RP Freezer
            [1, 4, 2, 12, 2,10_000, 60],  // Karim agro:      potato   → Cold Room, RP Bulk Pallet
            [2, 5, 2, 13, 2, 8_000,  7],  // Fatema:          onion    → Cold Room, RP Bulk Pallet
            [3, 7, 1,  7, 4,   500, 90],  // Sunrise pharma:  vials    → Chiller B, RP Pharma
            [4, 2, 0,  2, 1, 6_000, 20],  // Delta poultry:   chicken  → Freezer A, RP Freezer
            [0, 6, 2, 14, 0,   200, 10],  // Rahim fisheries: mango    → Cold Room, RP Standard
        ];

        foreach ($lots as $idx => [$ci, $pi, $chi, $ui, $ri, $qty, $daysAgo]) {
            $customer   = $customers[$ci];
            $product    = $products[$pi];
            $chamber    = $chambers[$chi];
            $unit       = $storageUnits[$ui] ?? $storageUnits[0];
            $ratePlan   = $ratePlans[$ri] ?? $ratePlans[0];
            $lotCode    = sprintf('LOT-%s-%06d', Carbon::now()->format('Y'), $idx + 1);
            $receivedAt = Carbon::now()->subDays($daysAgo);

            if (StockLot::where('branch_id', $branch->id)->where('lot_code', $lotCode)->exists()) {
                continue;
            }

            $lot = StockLot::create([
                'tenant_id'        => $tenant->id,
                'branch_id'        => $branch->id,
                'customer_id'      => $customer->id,
                'product_id'       => $product->id,
                'chamber_id'       => $chamber->id,
                'storage_unit_id'  => $unit->id,
                'rate_plan_id'     => $ratePlan->id,
                'lot_code'         => $lotCode,
                'status'           => 'in_storage',
                'unit_of_measure'  => $product->unit_of_measure,
                'initial_quantity' => $qty,
                'quantity'         => $qty,
                'received_at'      => $receivedAt,
            ]);

            StockMovement::create([
                'tenant_id'          => $tenant->id,
                'branch_id'          => $branch->id,
                'lot_id'             => $lot->id,
                'type'               => 'stock_in',
                'quantity'           => $qty,
                'balance_after'      => $qty,
                'to_chamber_id'      => $chamber->id,
                'to_storage_unit_id' => $unit->id,
                'reference'          => 'DEMO-INTAKE-' . str_pad((string) ($idx + 1), 3, '0', STR_PAD_LEFT),
                'occurred_at'        => $receivedAt,
            ]);

            // Update storage unit occupancy.
            $unit->increment('occupied_weight_kg', $qty);
            if ((float) $unit->occupied_weight_kg >= (float) $unit->capacity_weight_kg) {
                $unit->update(['status' => 'occupied']);
            }
        }
    }

    // ── Billing data ─────────────────────────────────────────────────────

    private function seedBillingData(Tenant $tenant, Branch $branch, array $customers): void
    {
        // One issued invoice per customer with sample lines and a partial payment.
        foreach ($customers as $idx => $customer) {
            $invoiceNumber = sprintf('INV-%d-%04d', now()->year, $idx + 1);

            if (Invoice::where('tenant_id', $tenant->id)->where('invoice_number', $invoiceNumber)->exists()) {
                continue;
            }

            $subtotal = fake()->numberBetween(20_000_00, 200_000_00); // 2,000–20,000 BDT
            $tax      = (int) ceil($subtotal * 0.15);
            $total    = $subtotal + $tax;

            $invoice = Invoice::create([
                'tenant_id'       => $tenant->id,
                'branch_id'       => $branch->id,
                'customer_id'     => $customer->id,
                'invoice_number'  => $invoiceNumber,
                'status'          => 'issued',
                'issue_date'      => Carbon::now()->subDays(30 - $idx * 5),
                'due_date'        => Carbon::now()->addDays(30),
                'period_start'    => Carbon::now()->subDays(60),
                'period_end'      => Carbon::now()->subDays(30),
                'subtotal_poisha' => $subtotal,
                'tax_poisha'      => $tax,
                'total_poisha'    => $total,
                'amount_paid_poisha' => 0,
                'amount_due_poisha'  => $total,
                'currency'        => 'BDT',
                'notes'           => 'Storage charges for the period. Demo invoice.',
            ]);

            // Update customer outstanding balance.
            $customer->increment('balance_poisha', $total);

            // For every other customer add a partial payment.
            if ($idx % 2 === 0) {
                $paid   = (int) ($total * 0.5);
                $unpaid = $total - $paid;

                $payment = Payment::create([
                    'tenant_id'          => $tenant->id,
                    'branch_id'          => $branch->id,
                    'customer_id'        => $customer->id,
                    'payment_number'     => sprintf('RCV-%d-%04d', now()->year, $idx + 1),
                    'amount_poisha'      => $paid,
                    'allocated_poisha'   => $paid,  // fully allocated on creation
                    'method'             => 'bank_transfer',
                    'status'             => 'completed',
                    'paid_at'            => Carbon::now()->subDays(15),
                    'reference'          => 'TXN-DEMO-' . ($idx + 1),
                    'notes'              => 'Partial payment — demo seeder.',
                ]);

                // Allocate payment to invoice.
                \App\Models\PaymentAllocation::create([
                    'tenant_id'      => $tenant->id,
                    'payment_id'     => $payment->id,
                    'invoice_id'     => $invoice->id,
                    'amount_poisha'  => $paid,
                ]);

                $invoice->update([
                    'amount_paid_poisha' => $paid,
                    'amount_due_poisha'  => $unpaid,
                    'status'             => 'partially_paid',
                ]);

                $customer->decrement('balance_poisha', $paid);
            }
        }
    }
}
