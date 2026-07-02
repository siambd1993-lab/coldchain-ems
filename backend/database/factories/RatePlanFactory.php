<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\RatePlan;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RatePlan>
 */
class RatePlanFactory extends Factory
{
    protected $model = RatePlan::class;

    public function definition(): array
    {
        static $seq = 1;

        return [
            'tenant_id'              => Tenant::factory(),
            'code'                   => 'RP-' . str_pad((string) $seq++, 3, '0', STR_PAD_LEFT),
            'name'                   => $this->faker->words(3, true) . ' Plan',
            'billing_method'         => 'per_kg_per_month',
            'rate_poisha'            => $this->faker->numberBetween(50, 500),   // 0.50–5.00 BDT per unit
            'minimum_charge_poisha'  => $this->faker->numberBetween(0, 50_000), // 0–500 BDT min
            'grace_days'             => $this->faker->randomElement([0, 1, 2, 3]),
            'tax_rate'               => '15.00',   // Bangladesh standard VAT
            'is_active'              => true,
        ];
    }

    public function perKgPerDay(): static
    {
        return $this->state([
            'billing_method' => 'per_kg_per_day',
            'rate_poisha'    => $this->faker->numberBetween(5, 50),
        ]);
    }

    public function perSlotPerMonth(): static
    {
        return $this->state([
            'billing_method' => 'per_slot_per_month',
            'rate_poisha'    => $this->faker->numberBetween(1_000_00, 5_000_00),
        ]);
    }

    public function flatMonthly(): static
    {
        return $this->state([
            'billing_method' => 'flat_monthly',
            'rate_poisha'    => $this->faker->numberBetween(10_000_00, 100_000_00),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function noTax(): static
    {
        return $this->state(['tax_rate' => '0.00']);
    }
}
