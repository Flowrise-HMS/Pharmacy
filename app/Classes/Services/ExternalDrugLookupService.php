<?php

namespace Modules\Pharmacy\Classes\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Modules\Pharmacy\Models\Drug;

class ExternalDrugLookupService
{
    public function __construct(
        protected RxNormService $rxNormService,
        protected OpenFdaService $openFdaService
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function search(string $query, string $provider = 'rxnorm', int $limit = 10): array
    {
        $query = trim($query);

        if (Str::length($query) < 3) {
            return [];
        }

        $normalizedQuery = preg_replace('/[^a-z0-9]+/', '', Str::lower($query)) ?: 'empty';
        $cacheKey = "drug_search:{$provider}:{$normalizedQuery}";

        return Cache::remember($cacheKey, now()->addDays(7), function () use ($provider, $query, $limit): array {
            return match ($provider) {
                'openfda' => $this->mapOpenFdaResults($this->openFdaService->search($query), $limit),
                default => $this->mapRxNormResults($this->rxNormService->search($query), $limit),
            };
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $results
     * @return array<int, array<string, mixed>>
     */
    protected function mapRxNormResults(array $results, int $limit): array
    {
        return collect($results)
            ->take($limit)
            ->map(fn (array $result): array => $this->persistExternalDrug([
                'source' => 'external',
                'source_provider' => 'rxnorm',
                'source_identifier' => (string) ($result['rxcui'] ?? ''),
                'display_name' => $result['name'] ?? 'Unknown Medication',
                'generic_name' => $result['name'] ?? 'Unknown Medication',
                'brand_name' => null,
                'strength_text' => null,
                'dosage_form_text' => null,
                'rxnorm_code' => (string) ($result['rxcui'] ?? ''),
                'ndc_code' => null,
                'is_cached_external' => true,
                'medication_id' => null,
                'service_id' => null,
            ]))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $results
     * @return array<int, array<string, mixed>>
     */
    protected function mapOpenFdaResults(array $results, int $limit): array
    {
        return collect($results)
            ->take($limit)
            ->map(fn (array $result): array => $this->persistExternalDrug([
                'source' => 'external',
                'source_provider' => 'openfda',
                'source_identifier' => (string) ($result['ndc'] ?? ''),
                'display_name' => $result['name'] ?? 'Unknown Medication',
                'generic_name' => $result['name'] ?? 'Unknown Medication',
                'brand_name' => $result['brand_name'] ?? null,
                'strength_text' => null,
                'dosage_form_text' => $result['dosage_form'] ?? null,
                'rxnorm_code' => null,
                'ndc_code' => $result['ndc'] ?? null,
                'is_cached_external' => true,
                'medication_id' => null,
                'service_id' => null,
            ]))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function persistExternalDrug(array $payload): array
    {
        $drug = Drug::query()->updateOrCreate(
            [
                'source_provider' => $payload['source_provider'],
                'source_identifier' => $payload['source_identifier'],
            ],
            [
                'rxnorm_code' => $payload['rxnorm_code'],
                'ndc_code' => $payload['ndc_code'],
                'generic_name' => $payload['generic_name'],
                'display_name' => $payload['display_name'],
                'brand_name' => $payload['brand_name'],
                'strength_text' => $payload['strength_text'],
                'dosage_form_text' => $payload['dosage_form_text'],
                'is_cached_external' => true,
                'is_active' => true,
                'raw_payload' => $payload,
            ]
        );

        $payload['drug_id'] = $drug->id;

        return $payload;
    }
}
