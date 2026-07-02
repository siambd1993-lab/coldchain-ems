<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A raw time-series sample. Append-only and high-volume; it carries its own
 * `recorded_at`/`ingested_at` timestamps rather than Eloquent's created/updated
 * pair. Aggregated into {@see TelemetryRollup} by the scheduled rollup command.
 *
 * @property int $id
 * @property string $metric
 * @property float $value
 * @property \Illuminate\Support\Carbon $recorded_at
 */
class TelemetryReading extends Model
{
    use BelongsToBranch;
    use BelongsToTenant;
    use HasFactory;

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:4',
            'recorded_at' => 'datetime',
            'ingested_at' => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(DeviceChannel::class, 'channel_id');
    }

    public function chamber(): BelongsTo
    {
        return $this->belongsTo(Chamber::class);
    }
}
