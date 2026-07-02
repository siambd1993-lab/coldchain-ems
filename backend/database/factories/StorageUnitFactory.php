<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Chamber;
use App\Models\StorageUnit;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StorageUnit>
 */
class StorageUnitFactory extends Factory
{
    protected $model = StorageUnit::class;

    public function definition(): array
    {
        static $seq = 1;

        return [
            'tenant_id'          => Tenant::factory(),
            'branch_id'          => Branch::factory(),
            'chamber_id'         => Chamber::factory(),
            'code'               => 'SU-' . str_pad((string) $seq++, 4, '0', STR_PAD_LEFT),
            'label'              => 'Unit ' . $seq,
            'unit_type'          => $this->faker->randomElement([
                'rack', 'shelf', 'pallet_position', 'bin', 'floor_space',
            ]),
            'status'             => 'available',
            'capacity_weight_kg' => $this->faker->randomFloat(1, 100, 5_000),
            'capacity_volume_m3' => $this->faker->randomFloat(1, 1, 50),
            'occupied_weight_kg' => 0,
        ];
    }

    public function occupied(): static
    {
        return $this->state(['status' => 'occupied']);
    }

    public function reserved(): static
    {
        return $this->state(['status' => 'reserved']);
    }

    public function maintenance(): static
    {
        return $this->state(['status' => 'maintenance']);
    }

    public function palletPosition(): static
    {
        return $this->state(['unit_type' => 'pallet_position']);
    }
}
