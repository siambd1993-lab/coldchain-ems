<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\StockLot;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<StockLot>
 */
class StockLotFactory extends Factory
{
    protected $model = StockLot::class;

    public function definition(): array
    {
        static $seq = 1;
        $qty = $this->faker->randomFloat(1, 100, 10_000);

        return [
            'tenant_id'        => Tenant::factory(),
            'branch_id'        => Branch::factory(),
            'customer_id'      => Customer::factory(),
            'product_id'       => null,
            'chamber_id'       => null,
            'storage_unit_id'  => null,
            'rate_plan_id'     => null,
            'lot_code'         => 'LOT-' . str_pad((string) $seq++, 6, '0', STR_PAD_LEFT),
            'status'           => 'in_storage',
            'unit_of_measure'  => 'kg',
            'initial_quantity' => $qty,
            'quantity'         => $qty,
            'received_at'      => Carbon::now()->subDays($this->faker->numberBetween(1, 90)),
        ];
    }

    /** Lot is fully released. */
    public function released(): static
    {
        return $this->state(fn (): array => [
            'status'      => 'released',
            'quantity'    => 0,
            'released_at' => Carbon::now()->subDays($this->faker->numberBetween(0, 30)),
        ]);
    }

    /** Lot partially released — some quantity still in storage. */
    public function partiallyReleased(): static
    {
        return $this->state(fn (array $attrs): array => [
            'status'   => 'partially_released',
            'quantity' => round((float) ($attrs['initial_quantity'] ?? 500) * 0.4, 1),
        ]);
    }

    /** Lot is in storage (default state). */
    public function inStorage(): static
    {
        return $this->state(['status' => 'in_storage']);
    }
}
