<?php

namespace Modules\Pharmacy\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\Branch;
use Modules\Pharmacy\Models\Medication;
use Modules\Pharmacy\Models\StockItem;

class StockItemFactory extends Factory
{
    protected $model = StockItem::class;

    public function definition(): array
    {
        return [
            'medication_id' => Medication::factory(),
            'branch_id' => Branch::factory(),
            'quantity_on_hand' => fake()->numberBetween(10, 200),
            'reorder_point' => fake()->numberBetween(5, 50),
        ];
    }
}
