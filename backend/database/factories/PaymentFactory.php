<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        static $seq = 1;
        $amount = $this->faker->numberBetween(5_000, 200_000);

        return [
            'tenant_id'          => Tenant::factory(),
            'branch_id'          => Branch::factory(),
            'customer_id'        => Customer::factory(),
            'payment_number'     => sprintf('RCV-%d-%04d', now()->year, $seq++),
            'method'             => $this->faker->randomElement(['cash', 'bkash', 'bank_transfer', 'cheque']),
            'status'             => 'completed',
            'currency'           => 'BDT',
            'amount_poisha'      => $amount,
            'allocated_poisha'   => 0,
            'paid_at'            => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state(['status' => 'pending']);
    }

    public function fullyAllocated(): static
    {
        return $this->state(fn (array $attrs): array => [
            'allocated_poisha' => $attrs['amount_poisha'],
        ]);
    }

    public function bkash(): static
    {
        return $this->state(['method' => 'bkash', 'gateway' => 'bkash']);
    }
}
