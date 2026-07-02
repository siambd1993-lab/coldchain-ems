<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A single measured signal on a device (e.g. "temp_1" → temperature °C).
 * Optional per-channel thresholds override the parent chamber's set-point band.
 *
 * @property int $id
 * @property int $device_id
 * @property string $channel_key
 * @property string $metric
 * @property float|null $min_threshold
 * @property float|null $max_threshold
 */
class DeviceChannel extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'min_threshold' => 'decimal:4',
            'max_threshold' => 'decimal:4',
            'last_value' => 'decimal:4',
            'last_value_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function chamber(): BelongsTo
    {
        return $this->belongsTo(Chamber::class);
    }

    public function readings(): HasMany
    {
        return $this->hasMany(TelemetryReading::class, 'channel_id');
    }

    /** Does a value breach this channel's own configured thresholds? */
    public function isBreach(float $value): bool
    {
        if ($this->min_threshold !== null && $value < (float) $this->min_threshold) {
            return true;
        }

        return $this->max_threshold !== null && $value > (float) $this->max_threshold;
    }
}
