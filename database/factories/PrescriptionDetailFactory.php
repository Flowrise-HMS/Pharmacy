<?php

namespace Modules\Pharmacy\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Clinical\Models\RequestItem;
use Modules\Pharmacy\Enums\AdministrationContext;
use Modules\Pharmacy\Models\PrescriptionDetail;

class PrescriptionDetailFactory extends Factory
{
    protected $model = PrescriptionDetail::class;

    public function definition(): array
    {
        return [
            'request_item_id' => RequestItem::factory(),
            'dosage' => fake()->randomElement(['1 tablet', '2 capsules', '5 ml', '1 injection']),
            'dose_amount' => fake()->randomFloat(2, 0.5, 10),
            'frequency' => fake()->randomElement(['once_daily', 'twice_daily', 'three_times_daily', 'every_8_hours']),
            'route' => fake()->randomElement(['oral', 'intravenous', 'intramuscular', 'topical']),
            'duration_days' => fake()->numberBetween(3, 14),
            'prn' => fake()->boolean(20),
            'refills' => fake()->numberBetween(0, 3),
            'administration_context' => AdministrationContext::TAKE_HOME,
            'total_administrations' => fake()->numberBetween(10, 60),
        ];
    }
}
