<?php

namespace Modules\Pharmacy\Classes\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class RxNormService
{
    private const BASE_URL = 'https://rxnav.nlm.nih.gov/REST';

    /**
     * Search for RxCUIs based on a drug name using the Prescribable RxNorm API.
     */
    public function search(string $query): array
    {
        if (strlen(trim($query)) < 2) {
            return [];
        }

        $query = trim($query);
        $cacheKey = 'rxnorm_search_v6_'.preg_replace('/[^a-z0-9]/', '', strtolower($query));

        return Cache::remember($cacheKey, now()->addDays(7), function () use ($query) {
            try {
                $url = self::BASE_URL.'/Prescribe/rxcui.json?'.http_build_query([
                    'name' => $query,
                    'search' => 9,      // Approximate match
                    'srclist' => 'ALL',
                ]);

                $response = Http::timeout(10)->get($url);

                if (! $response->successful()) {
                    return [];
                }

                $cuis = $response->json('idGroup.rxnormId', []);

                if (empty($cuis)) {
                    return [];
                }

                $cuis = array_slice((array) $cuis, 0, 10);

                $results = [];
                foreach ($cuis as $cui) {
                    // Get full related info to extract nice prescribable names
                    $related = $this->getAllRelatedInfo($cui);

                    $added = false;

                    // Prefer SCD (Clinical Drug) and SBD (Branded Drug)
                    foreach ($related['conceptGroup'] ?? [] as $group) {
                        if (in_array($group['tty'] ?? '', ['SCD', 'SBD', 'SCDF', 'SBDP'])) {
                            foreach ($group['conceptProperties'] ?? [] as $prop) {
                                if (! empty($prop['name'])) {
                                    $results[] = [
                                        'rxcui' => $prop['rxcui'],
                                        'name' => $prop['name'],
                                        'tty' => $prop['tty'],
                                        'original_rxcui' => $cui,   // the one we searched
                                    ];
                                    $added = true;
                                }
                            }
                        }
                    }

                    // Fallback to basic properties if nothing found
                    if (! $added) {
                        $props = $this->getConceptProperties($cui);
                        if ($props) {
                            $results[] = [
                                'rxcui' => $cui,
                                'name' => $props['name'] ?? $query,
                                'tty' => $props['tty'] ?? null,
                            ];
                        }
                    }
                }

                // Remove duplicates and limit
                $results = collect($results)
                    ->unique('rxcui')
                    ->take(15)
                    ->values()
                    ->all();

                return $results;

            } catch (\Exception $e) {
                return [];
            }
        });
    }

    /**
     * Get properties for a specific RxCUI.
     */
    public function getConceptProperties(string $cui): ?array
    {
        return Cache::remember('rxnorm_props_'.$cui, now()->addDays(30), function () use ($cui) {
            try {
                $response = Http::timeout(8)
                    ->get(self::BASE_URL."/Prescribe/rxcui/{$cui}/properties.json");

                if ($response->successful()) {
                    return $response->json('properties');
                }
            } catch (\Exception $e) {
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
                $response = Http::timeout(10)
                    ->get(self::BASE_URL."/Prescribe/rxcui/{$cui}/allrelated.json");

                return $response->successful()
                    ? $response->json('allRelatedGroup', [])
                    : [];
            } catch (\Exception $e) {
                return [];
            }
        });
    }
}
