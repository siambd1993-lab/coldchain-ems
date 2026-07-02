<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payment
 */
class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'payment_number'   => $this->payment_number,
            'branch_id'        => $this->branch_id,
            'customer_id'      => $this->customer_id,
            'method'           => $this->method,
            'status'           => $this->status,
            'currency'         => $this->currency,

            // All amounts in poisha.
            'amount_poisha'     => (int) $this->amount_poisha,
            'allocated_poisha'  => (int) $this->allocated_poisha,
            'unallocated_poisha' => $this->unallocatedAmount(),

            'reference'        => $this->reference,
            'gateway'          => $this->gateway,
            'notes'            => $this->notes,

            'received_by'      => $this->received_by,
            'paid_at'          => $this->paid_at?->toIso8601String(),
            'refunded_at'      => $this->refunded_at?->toIso8601String(),

            // Embedded relations — only when loaded.
            'customer'         => CustomerResource::make($this->whenLoaded('customer')),
            'allocations'      => $this->whenLoaded('allocations', fn () => $this->allocations->map(fn ($a) => [
                'id'            => $a->id,
                'invoice_id'    => $a->invoice_id,
                'amount_poisha' => (int) $a->amount_poisha,
            ])),

            'created_at'       => $this->created_at?->toIso8601String(),
            'updated_at'       => $this->updated_at?->toIso8601String(),
        ];
    }
}
