<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A commodity/product type that can be stored (potato, fish, vaccine, …).
 * Carries recommended storage envelope used to validate chamber placement.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $code
 * @property string $name
 * @property string $unit_of_measure
 */
class Product extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'default_temp_min_c' => 'decimal:2',
            'default_temp_max_c' => 'decimal:2',
            'shelf_life_days' => 'integer',
            'attributes' => 'array',
        ];
    }

    public function lots(): HasMany
    {
        return $this->hasMany(StockLot::class);
    }
}
