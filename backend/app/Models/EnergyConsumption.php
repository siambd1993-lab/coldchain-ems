<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Daily aggregated energy usage & cost for a branch (optionally per chamber or
 * device), split by source. Cost is poisha.
 *
 * @property int $id
 * @property \Illuminate\Support\Carbon $date
 * @property float $energy_kwh
 * @property int $cost_poisha
 */
class EnergyConsumption extends Model
{
    use BelongsToBranch;
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'energy_consumptions';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'energy_kwh' => 'decimal:4',
            'peak_demand_kw' => 'decimal:4',
            'cost_poisha' => 'integer',
            'co2_kg' => 'decimal:3',
            'metadata' => 'array',
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
}
