<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Money received from a customer (cash or MFS gateway). Its amount may be split
 * across several invoices via {@see PaymentAllocation}; `allocated_poisha`
 * tracks how much has been applied.
 *
 * @property int $id
 * @property string $payment_number
 * @property string $method
 * @property string $status
 * @property int $amount_poisha
 * @property int $allocated_poisha
 */
class Payment extends Model
{
    use BelongsToBranch;
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount_poisha' => 'integer',
            'allocated_poisha' => 'integer',
            'gateway_payload' => 'array',
            'paid_at' => 'datetime',
            'refunded_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /** Portion of the payment not yet applied to any invoice (poisha). */
    public function unallocatedAmount(): int
    {
        return max(0, $this->amount_poisha - $this->allocated_poisha);
    }
}
