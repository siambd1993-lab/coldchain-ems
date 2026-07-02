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
 * A physical IoT endpoint: sensor, gateway, PLC, BMS, inverter, or energy meter.
 * Only the hash of its ingest credential is stored (`auth_token_hash`).
 *
 * @property int $id
 * @property string $device_uid
 * @property string $device_type
 * @property string $status
 */
class Device extends Model
{
    use BelongsToBranch;
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $hidden = ['auth_token_hash'];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'last_heartbeat_at' => 'datetime',
            'config' => 'array',
            'metadata' => 'array',
        ];
    }

    public function chamber(): BelongsTo
    {
        return $this->belongsTo(Chamber::class);
    }

    public function channels(): HasMany
    {
        return $this->hasMany(DeviceChannel::class);
    }

    public function readings(): HasMany
    {
        return $this->hasMany(TelemetryReading::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    public function isOnline(): bool
    {
        return $this->status === 'online';
    }

    public static function hashToken(string $plaintext): string
    {
        return hash('sha256', $plaintext);
    }
}
