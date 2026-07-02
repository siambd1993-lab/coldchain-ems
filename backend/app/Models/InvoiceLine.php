<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single charge line on an invoice. `amount_poisha` = qty*unit_price - discount
 * (+ tax), computed by the billing service when the line is built.
 *
 * @property int $id
 * @property int $invoice_id
 * @property float $quantity
 * @property int $unit_price_poisha
 * @property int $amount_poisha
 */
class InvoiceLine extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price_poisha' => 'integer',
            'discount_poisha' => 'integer',
            'tax_rate' => 'decimal:2',
            'tax_poisha' => 'integer',
            'amount_poisha' => 'integer',
            'period_start' => 'date',
            'period_end' => 'date',
            'metadata' => 'array',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(StockLot::class, 'lot_id');
    }

    public function ratePlan(): BelongsTo
    {
        return $this->belongsTo(RatePlan::class);
    }
}
