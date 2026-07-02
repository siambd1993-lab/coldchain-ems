<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\InvoiceLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin InvoiceLine
 */
class InvoiceLineResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'invoice_id'         => $this->invoice_id,
            'lot_id'             => $this->lot_id,
            'rate_plan_id'       => $this->rate_plan_id,
            'description'        => $this->description,
            'quantity'           => (float) $this->quantity,
            'unit'               => $this->unit,
            'unit_price_poisha'  => (int) $this->unit_price_poisha,
            'discount_poisha'    => (int) $this->discount_poisha,
            'tax_rate'           => (float) $this->tax_rate,
            'tax_poisha'         => (int) $this->tax_poisha,
            'amount_poisha'      => (int) $this->amount_poisha,
            'period_start'       => $this->period_start?->toDateString(),
            'period_end'         => $this->period_end?->toDateString(),
            'metadata'           => $this->metadata,
            'created_at'         => $this->created_at?->toIso8601String(),
        ];
    }
}
