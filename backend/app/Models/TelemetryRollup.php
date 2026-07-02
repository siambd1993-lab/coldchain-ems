<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pre-aggregated telemetry for a (channel, window, bucket) tuple — powers
 * dashboards and trend charts without scanning raw readings.
 *
 * @property int $id
 * @property string $metric
 * @property string $window
 * @property \Illuminate\Support\Carbon $bucket_start
 * @property float|null $avg_value
 */
class TelemetryRollup extends Model
{
    use BelongsToBranch;
    use BelongsToTenant;
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'bucket_start' => 'datetime',
            'sample_count' => 'integer',
            'min_value' => 'decimal:4',
            'max_value' => 'decimal:4',
            'avg_value' => 'decimal:4',
            'sum_value' => 'decimal:4',
        ];
    }

    public function chamber(): BelongsTo
    {
        return $this->belongsTo(Chamber::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(DeviceChannel::class, 'channel_id');
    }
}
