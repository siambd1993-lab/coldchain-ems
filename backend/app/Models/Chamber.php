<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A temperature-controlled room within a branch.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $branch_id
 * @property string $code
 * @property string $chamber_type
 * @property string $status
 * @property float|null $target_temp_min_c
 * @property float|null $target_temp_max_c
 * @property float|null $current_temp_c
 */
class Chamber extends Model
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
            'capacity_slots' => 'integer',
            'area_sqft' => 'decimal:2',
            'target_temp_min_c' => 'decimal:2',
            'target_temp_max_c' => 'decimal:2',
            'target_humidity_min' => 'decimal:2',
            'target_humidity_max' => 'decimal:2',
            'current_temp_c' => 'decimal:2',
            'current_humidity' => 'decimal:2',
            'readings_updated_at' => 'datetime',
            'settings' => 'array',
        ];
    }

    public function storageUnits(): HasMany
    {
        return $this->hasMany(StorageUnit::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function lots(): HasMany
    {
        return $this->hasMany(StockLot::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    public function isOperational(): bool
    {
        return $this->status === 'operational';
    }

    /**
     * Whether a temperature reading sits inside the configured set-point band.
     * Returns null when no band is configured (nothing to judge against).
     */
    public function isTemperatureWithinBand(float $celsius): ?bool
    {
        if ($this->target_temp_min_c === null && $this->target_temp_max_c === null) {
            return null;
        }

        $min = $this->target_temp_min_c ?? -INF;
        $max = $this->target_temp_max_c ?? INF;

        return $celsius >= (float) $min && $celsius <= (float) $max;
    }
}
