<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A tariff: how storage is charged (per kg/day, per slot/month, flat, …).
 * `rate_poisha` is the price per unit per period in minor units.
 *
 * @property int $id
 * @property string $code
 * @property string $billing_method
 * @property int $rate_poisha
 * @property int $minimum_charge_poisha
 * @property float $tax_rate
 */
class RatePlan extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'rate_poisha' => 'integer',
            'minimum_charge_poisha' => 'integer',
            'grace_days' => 'integer',
            'tax_rate' => 'decimal:2',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function lots(): HasMany
    {
        return $this->hasMany(StockLot::class);
    }

    /** Does this tariff bill by elapsed days (vs. by month/flat)? */
    public function isDailyMethod(): bool
    {
        return str_ends_with($this->billing_method, '_per_day');
    }

    public function isWeightBased(): bool
    {
        return str_starts_with($this->billing_method, 'per_kg');
    }
}
