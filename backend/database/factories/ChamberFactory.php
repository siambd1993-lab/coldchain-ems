<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Chamber;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Chamber>
 */
class ChamberFactory extends Factory
{
    protected $model = Chamber::class;

    public function definition(): array
    {
        static $seq = 1;

        [$minTemp, $maxTemp] = $this->faker->randomElement([
            [-25.0, -18.0], // deep-freeze
            [-5.0,   2.0],  // freezer
            [2.0,    8.0],  // chiller
            [8.0,   15.0],  // cool room
            [15.0,  25.0],  // ambient-controlled
        ]);

        return [
            'tenant_id'          => Tenant::factory(),
            'branch_id'          => Branch::factory(),
            'code'               => 'CHM-' . str_pad((string) $seq++, 3, '0', STR_PAD_LEFT),
            'name'               => 'Chamber ' . $seq,
            'chamber_type'       => $this->faker->randomElement([
                'freezer', 'chiller', 'cold_room', 'blast_freezer', 'ripening', 'ambient',
            ]),
            'status'             => 'operational',
            'capacity_weight_kg' => $this->faker->randomFloat(1, 1_000, 50_000),
            'capacity_volume_m3' => $this->faker->randomFloat(1, 50, 2_000),
            'capacity_slots'     => $this->faker->numberBetween(10, 200),
            'area_sqft'          => $this->faker->randomFloat(0, 200, 10_000),
            'target_temp_min_c'  => $minTemp,
            'target_temp_max_c'  => $maxTemp,
            'current_temp_c'     => $this->faker->randomFloat(2, $minTemp, $maxTemp),
        ];
    }

    public function freezer(): static
    {
        return $this->state([
            'chamber_type'      => 'freezer',
            'target_temp_min_c' => -25.0,
            'target_temp_max_c' => -18.0,
        ]);
    }

    public function chiller(): static
    {
        return $this->state([
            'chamber_type'      => 'chiller',
            'target_temp_min_c' => 2.0,
            'target_temp_max_c' => 8.0,
        ]);
    }

    public function offline(): static
    {
        return $this->state(['status' => 'offline']);
    }

    public function maintenance(): static
    {
        return $this->state(['status' => 'maintenance']);
    }
}
