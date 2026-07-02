<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        static $seq = 1;

        return [
            'tenant_id'       => Tenant::factory(),
            'branch_id'       => null,
            'code'            => 'CUST-' . str_pad((string) $seq++, 4, '0', STR_PAD_LEFT),
            'type'            => $this->faker->randomElement(['individual', 'business']),
            'name'            => $this->faker->company(),
            'contact_person'  => $this->faker->name(),
            'email'           => $this->faker->unique()->safeEmail(),
            'phone'           => '+8801' . $this->faker->numerify('#########'),
            'address_line1'   => $this->faker->streetAddress(),
            'city'            => $this->faker->city(),
            'district'        => $this->faker->state(),
            'country'         => 'BD',
            'credit_limit_poisha'   => $this->faker->randomElement([0, 100_000_00, 500_000_00, 1_000_000_00]),
            'opening_balance_poisha' => 0,
            'balance_poisha'        => 0,
            'status'          => 'active',
        ];
    }

    public function individual(): static
    {
        return $this->state([
            'type'           => 'individual',
            'name'           => $this->faker->name(),
            'contact_person' => null,
        ]);
    }

    public function business(): static
    {
        return $this->state(['type' => 'business']);
    }

    public function inactive(): static
    {
        return $this->state(['status' => 'inactive']);
    }

    public function withBalance(int $poisha): static
    {
        return $this->state(['balance_poisha' => $poisha]);
    }
}
