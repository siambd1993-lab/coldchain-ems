<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Links a slice of a {@see Payment} to a specific {@see Invoice}. The sum of a
 * payment's allocations equals its allocated_poisha; the sum of an invoice's
 * allocations equals its amount_paid_poisha.
 *
 * @property int $id
 * @property int $payment_id
 * @property int $invoice_id
 * @property int $amount_poisha
 */
class PaymentAllocation extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount_poisha' => 'integer',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
