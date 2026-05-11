<?php

namespace Modules\Pharmacy\Classes\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class RxNormService
{
    /**
     * Search for RxCUIs based on a drug name using the Prescribable RxNorm API.
     */
    public function search(string $query): array
    {
        if (strlen($query) < 3) {
            return [];
        }

        $cacheKey = 'rxnorm_search_v2_'.preg_replace('/[^a-z0-9]/', '', strtolower($query));

        return Cache::remember($cacheKey, now()->addDays(7), function () use ($query) {
            try {
                // Step 1: Find RxCUIs by string (Approximate match for better suggestions)
                $response = Http::timeout(5)->get('https://rxnav.nlm.nih.gov/REST/Prescribe/rxcui.json', [
                    'name' => $query,
                    'search' => 1, // 1 = Approximate match
                ]);

                if ($response->successful()) {
                    $cuis = $response->json('idGroup.rxnormId', []);

                    if (empty($cuis)) {
                        return [];
                    }

                    // Limit to top 10 results to avoid too many API calls
                    $cuis = array_slice($cuis, 0, 10);
                    $results = [];

                    foreach ($cuis as $cui) {
                        $conceptProps = $this->getConceptProperties($cui);
                        if ($conceptProps) {
                            $results[] = [
                                'rxcui' => $cui,
                                'name' => $conceptProps['name'] ?? 'Unknown',
                            ];
                        }
                    }

                    return $results;
                }
            } catch (\Exception $e) {
                return [];
            }

            return [];
        });
    }

    /**
     * Get properties for a specific RxCUI.
     */
    public function getConceptProperties(string $cui): ?array
    {
        return Cache::remember('rxnorm_concept_'.$cui, now()->addDays(30), function () use ($cui) {
            try {
                $response = Http::timeout(5)->get("https://rxnav.nlm.nih.gov/REST/Prescribe/rxcui/{$cui}/property.json");

                if ($response->successful()) {
                    return $response->json('propConceptGroup.propConcept.0', null);
                }
            } catch (\Exception $e) {
                return null;
            }

            return null;
        });
    }

    /**
     * Get related information for an RxCUI.
     */
    public function getAllRelatedInfo(string $cui): array
    {
        return Cache::remember('rxnorm_related_'.$cui, now()->addDays(30), function () use ($cui) {
            try {
                $response = Http::timeout(5)->get("https://rxnav.nlm.nih.gov/REST/Prescribe/rxcui/{$cui}/allrelated.json");

                if ($response->successful()) {
                    return $response->json('allRelatedGroup', []);
                }
            } catch (\Exception $e) {
                return [];
            }

            return [];
        });
    }
}
