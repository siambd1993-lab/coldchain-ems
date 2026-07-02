<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A cold-storage operator — the top of the tenancy tree and the isolation
 * boundary for every other record in the system.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $status
 */
class Tenant extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            // Per-tenant MFS/SMS secrets — transparently encrypted at rest.
            'integration_credentials' => 'encrypted:array',
            'trial_ends_at' => 'datetime',
            'subscription_ends_at' => 'datetime',
        ];
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['trial', 'active'], true);
    }

    public function isSuspended(): bool
    {
        return in_array($this->status, ['suspended', 'past_due', 'cancelled'], true);
    }

    /** Effective display timezone, falling back to the platform default. */
    public function effectiveTimezone(): string
    {
        return $this->timezone ?: config('app.display_timezone', 'Asia/Dhaka');
    }
}
