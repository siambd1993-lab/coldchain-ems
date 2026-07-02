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
use Illuminate\Support\Carbon;

/**
 * A batch of a customer's goods occupying storage. Its live `quantity` is the
 * running result of the append-only {@see StockMovement} ledger.
 *
 * @property int $id
 * @property string $lot_code
 * @property string $status
 * @property float $quantity
 * @property float|null $weight_kg
 * @property Carbon|null $received_at
 */
class StockLot extends Model
{
    use BelongsToBranch;
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'initial_quantity' => 'decimal:3',
            'quantity' => 'decimal:3',
            'initial_weight_kg' => 'decimal:3',
            'weight_kg' => 'decimal:3',
            'package_count' => 'integer',
            'received_at' => 'datetime',
            'expected_release_at' => 'date',
            'released_at' => 'datetime',
            'expiry_date' => 'date',
            'metadata' => 'array',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function chamber(): BelongsTo
    {
        return $this->belongsTo(Chamber::class);
    }

    public function storageUnit(): BelongsTo
    {
        return $this->belongsTo(StorageUnit::class);
    }

    public function ratePlan(): BelongsTo
    {
        return $this->belongsTo(RatePlan::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'lot_id');
    }

    public function isInStorage(): bool
    {
        return in_array($this->status, ['in_storage', 'partially_released'], true);
    }

    /** Whole days the lot has been (or was) in storage — drives day-based rent. */
    public function daysInStorage(?Carbon $until = null): int
    {
        if ($this->received_at === null) {
            return 0;
        }

        $end = $until ?? ($this->released_at ?? now());

        // Carbon 3's diffInDays returns float; both ends are start-of-day so the
        // value is whole — cast so the declared int return type holds.
        return max(1, (int) $this->received_at->startOfDay()->diffInDays($end->copy()->startOfDay()) + 1);
    }
}
