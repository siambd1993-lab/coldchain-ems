<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        $name = $this->faker->company() . ' Cold Storage';

        return [
            'name'          => $name,
            'slug'          => Str::slug($name) . '-' . $this->faker->unique()->numerify('###'),
            'legal_name'    => $name . ' Ltd.',
            'domain'        => null,
            'status'        => 'active',
            'plan'          => 'starter',
            'timezone'      => 'Asia/Dhaka',
            'currency'      => 'BDT',
            'locale'        => 'en',
            'contact_name'  => $this->faker->name(),
            'contact_email' => $this->faker->unique()->safeEmail(),
            'contact_phone' => '+8801' . $this->faker->numerify('#########'),
        ];
    }

    public function trial(): static
    {
        return $this->state(['status' => 'trial']);
    }

    public function suspended(): static
    {
        return $this->state(['status' => 'suspended']);
    }
}
