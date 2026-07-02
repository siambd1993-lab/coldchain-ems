<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A physical cold-storage facility belonging to a tenant. The unit of "where"
 * for branch-scoped access control.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $code
 * @property string $name
 * @property string $status
 */
class Branch extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'settings' => 'array',
        ];
    }

    public function chambers(): HasMany
    {
        return $this->hasMany(Chamber::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'branch_user')->withTimestamps();
    }

    public function isOperational(): bool
    {
        return $this->status === 'active';
    }

    public function effectiveTimezone(): string
    {
        return $this->timezone ?: $this->tenant?->effectiveTimezone() ?? config('app.display_timezone');
    }
}
