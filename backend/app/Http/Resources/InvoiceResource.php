<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Invoice
 */
class InvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var \App\Enums\InvoiceStatus $status */
        $status = $this->status;

        return [
            'id'             => $this->id,
            'invoice_number' => $this->invoice_number,
            'branch_id'      => $this->branch_id,
            'customer_id'    => $this->customer_id,
            'status'         => $status->value,
            'status_label'   => $status->label(),
            'is_overdue'     => $this->isOverdue(),
            'is_payable'     => $status->isPayable(),

            'currency'    => $this->currency,
            'issue_date'  => $this->issue_date?->toDateString(),
            'due_date'    => $this->due_date?->toDateString(),
            'period'      => [
                'start' => $this->period_start?->toDateString(),
                'end'   => $this->period_end?->toDateString(),
            ],

            // All amounts in poisha.
            'subtotal_poisha'    => (int) $this->subtotal_poisha,
            'discount_poisha'    => (int) $this->discount_poisha,
            'tax_poisha'         => (int) $this->tax_poisha,
            'total_poisha'       => (int) $this->total_poisha,
            'amount_paid_poisha' => (int) $this->amount_paid_poisha,
            'amount_due_poisha'  => (int) $this->amount_due_poisha,

            'notes'           => $this->notes,
            'issued_by'       => $this->issued_by,
            'issued_at'       => $this->issued_at?->toIso8601String(),
            'voided_at'       => $this->voided_at?->toIso8601String(),
            'void_reason'     => $this->void_reason,

            // Embedded relations — only when loaded.
            'customer'        => CustomerResource::make($this->whenLoaded('customer')),
            'lines'           => InvoiceLineResource::collection($this->whenLoaded('lines')),

            'created_at'      => $this->created_at?->toIso8601String(),
            'updated_at'      => $this->updated_at?->toIso8601String(),
        ];
    }
}
