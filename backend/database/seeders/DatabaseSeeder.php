<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Root seeder.
 *
 * Execution order:
 *   1. RoleSeeder — seeds the platform_admin role (null tenant) and refreshes
 *      system-role records for every tenant already in the database. Running
 *      this in production after adding new permissions is safe (upserts).
 *
 *   2. DemoSeeder — (non-production only) creates the Arcturus demo tenant with
 *      two branches, eight staff users covering all roles, five customers, eight
 *      stock lots, sample invoices, and partial payments.
 *
 * Run:
 *   php artisan db:seed                          # role + demo (non-production)
 *   php artisan db:seed --class=RoleSeeder       # role refresh only
 *   php artisan db:seed --class=DemoSeeder       # demo data only (dev/staging)
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        if (! app()->isProduction()) {
            $this->call(DemoSeeder::class);
        }
    }
}
