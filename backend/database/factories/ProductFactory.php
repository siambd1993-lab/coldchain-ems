<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /** Realistic cold-storage commodity catalogue. */
    private const COMMODITIES = [
        ['Fish',           'fish',       'kg',     -2.0,   2.0,   90],
        ['Shrimp',         'seafood',    'kg',     -20.0, -18.0, 180],
        ['Chicken',        'poultry',    'kg',     -18.0, -12.0,  60],
        ['Beef',           'meat',       'kg',     -18.0, -12.0, 365],
        ['Potato',         'vegetables', 'kg',      4.0,   8.0,  180],
        ['Onion',          'vegetables', 'kg',      2.0,   5.0,  120],
        ['Mango',          'fruits',     'crate',   8.0,  12.0,   21],
        ['Banana',         'fruits',     'crate',  12.0,  14.0,   14],
        ['Lychee',         'fruits',     'kg',      2.0,   5.0,   14],
        ['Ice Cream',      'dairy',      'carton', -18.0, -15.0, 365],
        ['Butter',         'dairy',      'kg',      4.0,   8.0,   90],
        ['Medicine A',     'pharma',     'carton',  2.0,   8.0,  730],
        ['Vaccine Batch',  'pharma',     'carton',  2.0,   8.0,  365],
    ];

    public function definition(): array
    {
        static $seq = 1;
        $c = self::COMMODITIES[($seq - 1) % count(self::COMMODITIES)];

        return [
            'tenant_id'          => Tenant::factory(),
            'code'               => 'PROD-' . str_pad((string) $seq++, 4, '0', STR_PAD_LEFT),
            'name'               => $c[0] . ' #' . $seq,
            'category'           => $c[1],
            'unit_of_measure'    => $c[2],
            'default_temp_min_c' => $c[3],
            'default_temp_max_c' => $c[4],
            'shelf_life_days'    => $c[5],
        ];
    }

    /** Fresh/chilled produce. */
    public function chilled(): static
    {
        return $this->state([
            'category'           => 'produce',
            'unit_of_measure'    => 'kg',
            'default_temp_min_c' => 2.0,
            'default_temp_max_c' => 8.0,
            'shelf_life_days'    => 30,
        ]);
    }

    /** Deep-frozen commodity. */
    public function frozen(): static
    {
        return $this->state([
            'category'           => 'frozen',
            'unit_of_measure'    => 'kg',
            'default_temp_min_c' => -20.0,
            'default_temp_max_c' => -18.0,
            'shelf_life_days'    => 180,
        ]);
    }

    /** Pharmaceutical product. */
    public function pharma(): static
    {
        return $this->state([
            'category'           => 'pharma',
            'unit_of_measure'    => 'carton',
            'default_temp_min_c' => 2.0,
            'default_temp_max_c' => 8.0,
            'shelf_life_days'    => 730,
        ]);
    }
}
