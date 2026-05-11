<?php

namespace Modules\Pharmacy\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Pharmacy\Models\Drug;

class DrugFactory extends Factory
{
    protected $model = Drug::class;

    public function definition(): array
    {
        $genericName = fake()->randomElement([
            'Amoxicillin',
            'Ibuprofen',
            'Paracetamol',
            'Metformin',
        ]);

        return [
            'source_provider' => fake()->randomElement(['local', 'rxnorm']),
            'source_identifier' => (string) fake()->unique()->numberBetween(10000, 99999),
            'rxnorm_code' => (string) fake()->optional()->numberBetween(10000, 99999),
            'ndc_code' => fake()->optional()->numerify('#####-####-##'),
            'generic_name' => $genericName,
            'display_name' => $genericName.' '.fake()->randomElement(['250 MG Tablet', '500 MG Capsule', '100 MG/5 ML Suspension']),
            'brand_name' => fake()->optional()->company(),
            'strength_text' => fake()->optional()->randomElement(['250 MG', '500 MG', '100 MG/5 ML']),
            'dosage_form_text' => fake()->optional()->randomElement(['Tablet', 'Capsule', 'Suspension']),
            'synonyms' => fake()->boolean() ? [fake()->word(), fake()->word()] : null,
            'raw_payload' => ['source' => 'factory'],
            'search_rank' => fake()->numberBetween(0, 100),
            'times_prescribed' => fake()->numberBetween(0, 20),
            'times_stocked' => fake()->numberBetween(0, 20),
            'is_cached_external' => fake()->boolean(),
            'is_active' => true,
        ];
    }
}
