<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A customer invoice. Amount columns are poisha. `amount_due_poisha` is kept in
 * sync on every write so receivables can be summed without joining payments.
 *
 * @property int $id
 * @property string $invoice_number
 * @property InvoiceStatus $status
 * @property int $total_poisha
 * @property int $amount_paid_poisha
 * @property int $amount_due_poisha
 */
class Invoice extends Model
{
    use BelongsToBranch;
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'issue_date' => 'date',
            'due_date' => 'date',
            'period_start' => 'date',
            'period_end' => 'date',
            'subtotal_poisha' => 'integer',
            'discount_poisha' => 'integer',
            'tax_poisha' => 'integer',
            'total_poisha' => 'integer',
            'amount_paid_poisha' => 'integer',
            'amount_due_poisha' => 'integer',
            'issued_at' => 'datetime',
            'voided_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function isOverdue(): bool
    {
        return $this->status->isPayable()
            && $this->due_date !== null
            && $this->due_date->isPast();
    }
}
