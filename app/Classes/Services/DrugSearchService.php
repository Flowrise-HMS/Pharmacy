<?php

namespace Modules\Pharmacy\Classes\Services;

use Illuminate\Support\Collection;
use Modules\Pharmacy\Models\Drug;
use Modules\Pharmacy\Models\Medication;

class DrugSearchService
{
    public function __construct(
        protected ExternalDrugLookupService $externalDrugLookupService
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function search(string $query, int $limit = 10): array
    {
        $query = trim($query);

        if (mb_strlen($query) === 0) {
            return $this->getTopLocalDrugs($limit);
        }

        if (mb_strlen($query) < 2) {
            return [];
        }

        $localDrugResults = $this->searchLocalDrugs($query, $limit);
        $localMedicationResults = $this->searchLocalMedications($query, $limit);

        $results = $localDrugResults->concat($localMedicationResults);

        if (config('pharmacy.enable_external_drug_lookup', false)) {
            $externalResults = collect($this->externalDrugLookupService->search($query, 'rxnorm', $limit));
            $results = $results->concat($externalResults);
        }

        return $results->take($limit)->values()->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getTopLocalDrugs(int $limit = 50): array
    {
        return Drug::query()
            ->where('is_active', true)
            ->orderByDesc('times_prescribed')
            ->orderByDesc('search_rank')
            ->limit($limit)
            ->get()
            ->map(fn (Drug $drug): array => [
                'source' => 'local_drug',
                'source_provider' => $drug->source_provider,
                'source_identifier' => $drug->source_identifier,
                'display_name' => $drug->display_name,
                'generic_name' => $drug->generic_name,
                'brand_name' => $drug->brand_name,
                'strength_text' => $drug->strength_text,
                'dosage_form_text' => $drug->dosage_form_text,
                'rxnorm_code' => $drug->rxnorm_code,
                'ndc_code' => $drug->ndc_code,
                'is_cached_external' => $drug->is_cached_external,
                'drug_id' => $drug->id,
                'medication_id' => null,
                'service_id' => null,
            ])
            ->toArray();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function searchLocalDrugs(string $query, int $limit): Collection
    {
        return Drug::query()
            ->where('is_active', true)
            ->where(function ($builder) use ($query): void {
                $builder->where('generic_name', 'like', "%{$query}%")
                    ->orWhere('display_name', 'like', "%{$query}%")
                    ->orWhere('brand_name', 'like', "%{$query}%")
                    ->orWhere('rxnorm_code', 'like', "%{$query}%")
                    ->orWhere('ndc_code', 'like', "%{$query}%");
            })
            ->orderByDesc('search_rank')
            ->orderByDesc('times_prescribed')
            ->limit($limit)
            ->get()
            ->map(fn (Drug $drug): array => [
                'source' => 'local_drug',
                'source_provider' => $drug->source_provider,
                'source_identifier' => $drug->source_identifier,
                'display_name' => $drug->display_name,
                'generic_name' => $drug->generic_name,
                'brand_name' => $drug->brand_name,
                'strength_text' => $drug->strength_text,
                'dosage_form_text' => $drug->dosage_form_text,
                'rxnorm_code' => $drug->rxnorm_code,
                'ndc_code' => $drug->ndc_code,
                'is_cached_external' => $drug->is_cached_external,
                'drug_id' => $drug->id,
                'medication_id' => null,
                'service_id' => null,
            ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function searchLocalMedications(string $query, int $limit): Collection
    {
        return Medication::query()
            ->where('is_active', true)
            ->where(function ($builder) use ($query): void {
                $builder->where('generic_name', 'like', "%{$query}%")
                    ->orWhere('brand_name', 'like', "%{$query}%")
                    ->orWhere('strength', 'like', "%{$query}%")
                    ->orWhere('rxnorm_code', 'like', "%{$query}%")
                    ->orWhere('ndc_code', 'like', "%{$query}%")
                    ->orWhereHas('service', fn ($serviceQuery) => $serviceQuery->where('name', 'like', "%{$query}%"));
            })
            ->with('service')
            ->limit($limit)
            ->get()
            ->map(fn (Medication $medication): array => [
                'source' => 'local_medication',
                'source_provider' => 'local',
                'source_identifier' => $medication->id,
                'display_name' => $medication->service?->name ?? $medication->generic_name,
                'generic_name' => $medication->generic_name,
                'brand_name' => $medication->brand_name,
                'strength_text' => $medication->strength,
                'dosage_form_text' => $medication->dosage_form?->value ?? (string) $medication->dosage_form,
                'rxnorm_code' => $medication->rxnorm_code,
                'ndc_code' => $medication->ndc_code,
                'is_cached_external' => false,
                'drug_id' => null,
                'medication_id' => $medication->id,
                'service_id' => $medication->service_id,
            ]);
    }
}
