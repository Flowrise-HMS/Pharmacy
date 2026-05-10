<?php

namespace Modules\Pharmacy\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Clinical\Models\RequestItem;
use Modules\Pharmacy\Models\Dispense;
use Modules\Pharmacy\Models\Medication;

class DispenseFactory extends Factory
{
    protected $model = Dispense::class;

    public function definition(): array
    {
        return [
            'request_item_id' => RequestItem::factory(),
            'medication_id' => Medication::factory(),
            'dispensed_by' => null,
            'quantity' => fake()->numberBetween(1, 30),
            'batch_number' => fake()->optional()->bothify('BATCH-####'),
            'expiry_date' => fake()->optional()->dateTimeBetween('+1 month', '+2 years'),
            'notes' => fake()->optional()->sentence(),
            'dispensed_at' => now(),
        ];
    }
}
