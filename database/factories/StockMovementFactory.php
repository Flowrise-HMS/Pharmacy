<?php

namespace Modules\Pharmacy\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\Branch;
use Modules\Pharmacy\Models\Medication;
use Modules\Pharmacy\Models\StockMovement;

class StockMovementFactory extends Factory
{
    protected $model = StockMovement::class;

    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'medication_id' => Medication::factory(),
            'delta' => $this->faker->numberBetween(-100, 100),
            'quantity_after' => $this->faker->numberBetween(0, 500),
            'reason' => $this->faker->randomElement(['purchase', 'sale', 'adjustment', 'return', 'expired']),
            'performed_by' => User::factory(),
        ];
    }
}
