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
 * A rentable subdivision of a chamber (rack, pallet position, bin, floor space).
 *
 * @property int $id
 * @property int $chamber_id
 * @property string $code
 * @property string $status
 * @property float|null $capacity_weight_kg
 * @property float $occupied_weight_kg
 */
class StorageUnit extends Model
{
    use BelongsToBranch;
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'capacity_weight_kg' => 'decimal:3',
            'capacity_volume_m3' => 'decimal:3',
            'occupied_weight_kg' => 'decimal:3',
            'settings' => 'array',
        ];
    }

    public function chamber(): BelongsTo
    {
        return $this->belongsTo(Chamber::class);
    }

    public function lots(): HasMany
    {
        return $this->hasMany(StockLot::class);
    }

    public function isAvailable(): bool
    {
        return $this->status === 'available';
    }

    /** Remaining weight capacity in kg, or null when capacity is uncapped. */
    public function availableCapacityKg(): ?float
    {
        if ($this->capacity_weight_kg === null) {
            return null;
        }

        return max(0.0, (float) $this->capacity_weight_kg - (float) $this->occupied_weight_kg);
    }
}
