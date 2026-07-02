<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A utility/source energy rate schedule (grid, generator, solar). `rate_poisha`
 * is the price per kWh; `time_of_use` optionally carries peak/off-peak bands.
 *
 * @property int $id
 * @property string $source
 * @property int $rate_poisha
 */
class EnergyTariff extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'rate_poisha' => 'integer',
            'demand_charge_poisha' => 'integer',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'time_of_use' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
