<?php

namespace Modules\Pharmacy\Classes\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class OpenFdaService
{
    /**
     * Search the OpenFDA NDC database for medications.
     *
     * @param  string  $query  The medication name (generic or brand)
     * @return array Array of medication results
     */
    public function search(string $query): array
    {
        if (strlen($query) < 3) {
            return [];
        }

        $cacheKey = 'openfda_search_'.preg_replace('/[^a-z0-9]/', '', strtolower($query));

        return Cache::remember($cacheKey, now()->addDays(7), function () use ($query) {
            try {
                // OpenFDA API uses Lucene query syntax
                $searchQuery = 'generic_name:"*'.$query.'*" OR brand_name:"*'.$query.'*"';

                $response = Http::timeout(5)->get('https://api.fda.gov/drug/ndc.json', [
                    'search' => $searchQuery,
                    'limit' => 10,
                ]);

                if ($response->successful()) {
                    $results = [];
                    $data = $response->json('results', []);

                    foreach ($data as $item) {
                        $genericName = $item['generic_name'] ?? '';
                        $brandName = $item['brand_name'] ?? '';

                        // Default to generic name, fallback to brand name
                        $name = ! empty($genericName) ? $genericName : $brandName;
                        if (! $name) {
                            continue;
                        }

                        $results[] = [
                            'ndc' => $item['product_ndc'] ?? '',
                            'name' => ucwords(strtolower($name)),
                            'brand_name' => ucwords(strtolower($brandName)),
                            'dosage_form' => $item['dosage_form'] ?? 'Unknown',
                            'route' => is_array($item['route'] ?? []) ? implode(', ', $item['route']) : ($item['route'] ?? ''),
                        ];
                    }

                    return $results;
                }
            } catch (\Exception $e) {
                // Fail silently to prevent breaking the clinical picker if API is down
                return [];
            }

            return [];
        });
    }
}
