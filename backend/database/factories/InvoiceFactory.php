<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        static $seq = 1;
        $year     = now()->year;
        $subtotal = $this->faker->numberBetween(10_000, 200_000);
        $tax      = (int) ceil($subtotal * 0.15);
        $total    = $subtotal + $tax;

        return [
            'tenant_id'          => Tenant::factory(),
            'branch_id'          => Branch::factory(),
            'customer_id'        => Customer::factory(),
            'invoice_number'     => sprintf('INV-%d-%04d', $year, $seq++),
            'status'             => 'draft',
            'issue_date'         => today(),
            'due_date'           => today()->addDays(30),
            'period_start'       => today()->subDays(30),
            'period_end'         => today(),
            'currency'           => 'BDT',
            'subtotal_poisha'    => $subtotal,
            'discount_poisha'    => 0,
            'tax_poisha'         => $tax,
            'total_poisha'       => $total,
            'amount_paid_poisha' => 0,
            'amount_due_poisha'  => $total,
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => 'draft']);
    }

    public function issued(): static
    {
        return $this->state(fn (array $attrs): array => [
            'status'      => 'issued',
            'issued_at'   => now(),
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attrs): array => [
            'status'             => 'paid',
            'amount_paid_poisha' => $attrs['total_poisha'],
            'amount_due_poisha'  => 0,
            'issued_at'          => now()->subDays(7),
        ]);
    }

    public function voided(): static
    {
        return $this->state([
            'status'     => 'void',
            'voided_at'  => now(),
            'void_reason' => 'Voided by test factory.',
        ]);
    }
}
