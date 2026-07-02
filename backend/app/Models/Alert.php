<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A monitoring incident (threshold breach, device fault, power failure) with an
 * acknowledge → resolve workflow. `dedupe_key` collapses a persisting condition
 * into a single active row.
 *
 * @property int $id
 * @property string $alert_type
 * @property string $severity
 * @property string $status
 * @property \Illuminate\Support\Carbon $triggered_at
 */
class Alert extends Model
{
    use BelongsToBranch;
    use BelongsToTenant;
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'threshold_value' => 'decimal:4',
            'observed_value' => 'decimal:4',
            'triggered_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'resolved_at' => 'datetime',
            'context' => 'array',
            'notified_channels' => 'array',
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

    public function acknowledger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function acknowledge(User $user): void
    {
        if ($this->status !== 'active') {
            return;
        }

        $this->update([
            'status' => 'acknowledged',
            'acknowledged_at' => now(),
            'acknowledged_by' => $user->getKey(),
        ]);
    }

    public function resolve(?User $user = null, ?string $note = null): void
    {
        if ($this->status === 'resolved') {
            return;
        }

        $this->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolved_by' => $user?->getKey(),
            'resolution_note' => $note,
        ]);
    }
}
