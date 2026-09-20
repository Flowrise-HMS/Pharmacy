<?php

namespace Modules\Pharmacy\Classes\Services;

use Illuminate\Support\Collection;
use Modules\Pharmacy\Models\Drug;
use Modules\Pharmacy\Models\Medication;
use Modules\Pharmacy\Settings\PharmacySettings;

/**
 * Unified medication picker feed. Formulary Medications always rank first;
 * reference Drugs (local catalog, then external lookups) only appear when no
 * Medication already represents them, so pickers never offer a duplicate path
 * to something the pharmacy already stocks.
 */
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

        $drugResults = $this->searchLocalDrugs($query, $limit);

        if ($this->externalLookupEnabled()) {
            $drugResults = $drugResults->concat(
                $this->externalDrugLookupService->search($query, 'rxnorm', $limit)
            );
        }

        return $this->searchLocalMedications($query, $limit)
            ->concat($this->rejectDrugsWithMedication($drugResults))
            ->take($limit)
            ->values()
            ->all();
    }

    protected function externalLookupEnabled(): bool
    {
        if (config('pharmacy.enable_external_drug_lookup', false)) {
            return true;
        }

        try {
            return app(PharmacySettings::class)->external_drug_lookup;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getTopLocalDrugs(int $limit = 50): array
    {
        $medications = Medication::query()
            ->where('is_active', true)
            ->with('service')
            ->limit($limit)
            ->get()
            ->map(fn (Medication $medication): array => $this->medicationResult($medication));

        $drugs = Drug::query()
            ->where('is_active', true)
            ->whereDoesntHave('medication')
            ->orderByDesc('times_prescribed')
            ->orderByDesc('search_rank')
            ->limit($limit)
            ->get()
            ->map(fn (Drug $drug): array => $this->drugResult($drug));

        return $medications
            ->concat($this->rejectDrugsWithMedication($drugs))
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function searchLocalDrugs(string $query, int $limit): Collection
    {
        return Drug::query()
            ->where('is_active', true)
            ->whereDoesntHave('medication')
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
            ->map(fn (Drug $drug): array => $this->drugResult($drug));
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
            ->orderByDesc('is_formulary')
            ->limit($limit)
            ->get()
            ->map(fn (Medication $medication): array => $this->medicationResult($medication));
    }

    /**
     * Drop drug rows (local or external) that a Medication already represents,
     * matched by explicit link or by shared RxNorm/NDC code, in one query.
     *
     * @param  Collection<int, array<string, mixed>>  $drugRows
     * @return Collection<int, array<string, mixed>>
     */
    protected function rejectDrugsWithMedication(Collection $drugRows): Collection
    {
        if ($drugRows->isEmpty()) {
            return $drugRows;
        }

        $drugIds = $drugRows->pluck('drug_id')->filter()->unique()->values();
        $rxnormCodes = $drugRows->pluck('rxnorm_code')->filter()->unique()->values();
        $ndcCodes = $drugRows->pluck('ndc_code')->filter()->unique()->values();

        $claimed = Medication::query()
            ->where(function ($builder) use ($drugIds, $rxnormCodes, $ndcCodes): void {
                $builder->whereIn('drug_id', $drugIds->all())
                    ->orWhereIn('rxnorm_code', $rxnormCodes->all())
                    ->orWhereIn('ndc_code', $ndcCodes->all());
            })
            ->get(['drug_id', 'rxnorm_code', 'ndc_code']);

        if ($claimed->isEmpty()) {
            return $drugRows;
        }

        $claimedDrugIds = $claimed->pluck('drug_id')->filter()->flip();
        $claimedRxnorm = $claimed->pluck('rxnorm_code')->filter()->flip();
        $claimedNdc = $claimed->pluck('ndc_code')->filter()->flip();

        return $drugRows->reject(function (array $row) use ($claimedDrugIds, $claimedRxnorm, $claimedNdc): bool {
            return (filled($row['drug_id'] ?? null) && $claimedDrugIds->has($row['drug_id']))
                || (filled($row['rxnorm_code'] ?? null) && $claimedRxnorm->has($row['rxnorm_code']))
                || (filled($row['ndc_code'] ?? null) && $claimedNdc->has($row['ndc_code']));
        })->values();
    }

    /**
     * @return array<string, mixed>
     */
    protected function drugResult(Drug $drug): array
    {
        return [
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
            'is_formulary' => false,
            'drug_id' => $drug->id,
            'medication_id' => null,
            'service_id' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function medicationResult(Medication $medication): array
    {
        return [
            'source' => 'local_medication',
            'source_provider' => 'local',
            'source_identifier' => $medication->id,
            'display_name' => $medication->service?->name ?? $medication->generic_name,
            'generic_name' => $medication->generic_name,
            'brand_name' => $medication->brand_name,
            'strength_text' => $medication->strength,
            'dosage_form_text' => enum_string($medication->dosage_form) ?? '',
            'rxnorm_code' => $medication->rxnorm_code,
            'ndc_code' => $medication->ndc_code,
            'is_cached_external' => false,
            'is_formulary' => $medication->is_formulary,
            'drug_id' => null,
            'medication_id' => $medication->id,
            'service_id' => $medication->service_id,
        ];
    }
}
