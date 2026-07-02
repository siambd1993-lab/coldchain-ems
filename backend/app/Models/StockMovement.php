<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StockMovementType;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One event in a lot's append-only stock ledger. Never updated or deleted; a
 * correction is a new {@see StockMovementType::Adjustment} row.
 *
 * @property int $id
 * @property int $lot_id
 * @property StockMovementType $type
 * @property float $quantity
 * @property float|null $balance_after
 */
class StockMovement extends Model
{
    use BelongsToBranch;
    use BelongsToTenant;
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
            'quantity' => 'decimal:3',
            'weight_kg' => 'decimal:3',
            'package_count' => 'integer',
            'balance_after' => 'decimal:3',
            'occurred_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(StockLot::class, 'lot_id');
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function fromChamber(): BelongsTo
    {
        return $this->belongsTo(Chamber::class, 'from_chamber_id');
    }

    public function toChamber(): BelongsTo
    {
        return $this->belongsTo(Chamber::class, 'to_chamber_id');
    }

    public function fromStorageUnit(): BelongsTo
    {
        return $this->belongsTo(StorageUnit::class, 'from_storage_unit_id');
    }

    public function toStorageUnit(): BelongsTo
    {
        return $this->belongsTo(StorageUnit::class, 'to_storage_unit_id');
    }
}
