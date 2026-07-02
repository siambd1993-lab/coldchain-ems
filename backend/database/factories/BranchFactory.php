<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    protected $model = Branch::class;

    public function definition(): array
    {
        static $seq = 1;

        return [
            'tenant_id'    => Tenant::factory(),
            'code'         => 'BR-' . str_pad((string) $seq++, 3, '0', STR_PAD_LEFT),
            'name'         => $this->faker->city() . ' Branch',
            'status'       => 'active',
            'address_line1' => $this->faker->streetAddress(),
            'city'         => $this->faker->city(),
            'district'     => $this->faker->state(),
            'country'      => 'BD',
            'phone'        => '+8801' . $this->faker->numerify('#########'),
            'timezone'     => null, // inherits tenant timezone
        ];
    }

    public function inactive(): static
    {
        return $this->state(['status' => 'inactive']);
    }

    public function underMaintenance(): static
    {
        return $this->state(['status' => 'under_maintenance']);
    }
}
