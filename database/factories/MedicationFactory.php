<?php

namespace Modules\Pharmacy\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\Service;
use Modules\Pharmacy\Enums\DosageForm;
use Modules\Pharmacy\Models\Medication;

class MedicationFactory extends Factory
{
    protected $model = Medication::class;

    public function definition(): array
    {
        return [
            'service_id' => Service::factory(),
            'rxnorm_code' => (string) fake()->numberBetween(100000, 999999),
            'ndc_code' => fake()->numerify('#####-####-##'),
            'generic_name' => fake()->word().' '.fake()->word(),
            'brand_name' => fake()->optional()->company(),
            'dosage_form' => fake()->randomElement(DosageForm::cases()),
            'strength' => fake()->randomElement(['250mg', '500mg', '5mg/5ml', '10mg']),
            'controlled_schedule' => null,
            'is_active' => true,
        ];
    }
}
