<?php

namespace Modules\Pharmacy\Classes\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OpenFdaService
{
    private const BASE_URL = 'https://api.fda.gov/drug/ndc.json';

    private const CACHE_TTL = 7;

    /**
     * Search the OpenFDA NDC database for medications.
     *
     * @param  string  $query  The medication name (generic or brand)
     * @return array Array of medication results
     */
    public function search(string $query): array
    {
        $query = trim($query);
        if (strlen($query) < 3) {
            return [];
        }

        $cacheKey = 'openfda_search_v2_'.md5(strtolower($query));

        return Cache::remember($cacheKey, now()->addDays(self::CACHE_TTL), function () use ($query) {
            try {
                // Better search syntax (more efficient than *query* on every character)
                $searchQuery = '(generic_name:"'.$query.'" OR brand_name:"'.$query.'" OR brand_name_base:"'.$query.'")';

                $response = Http::timeout(10)
                    ->get(self::BASE_URL, [
                        'search' => $searchQuery,
                        'limit' => 15,
                    ]);

                if (! $response->successful()) {
                    Log::warning('OpenFDA API error: '.$response->body());

                    return [];
                }

                $data = $response->json('results', []);

                $results = [];
                $seen = [];

                foreach ($data as $item) {
                    $generic = $item['generic_name'] ?? '';
                    $brand = $item['brand_name'] ?? $item['brand_name_base'] ?? '';
                    $name = $generic ?: $brand;

                    if (empty($name)) {
                        continue;
                    }

                    $ndc = $item['product_ndc'] ?? '';

                    // Avoid duplicates
                    if (isset($seen[$ndc])) {
                        continue;
                    }
                    $seen[$ndc] = true;

                    $results[] = [
                        'ndc' => $ndc,
                        'name' => Str::title($name),
                        'generic_name' => Str::title($generic),
                        'brand_name' => Str::title($brand),
                        'dosage_form' => $item['dosage_form'] ?? null,
                        'route' => $this->formatRoute($item['route'] ?? []),
                        'strength' => $this->extractStrength($item),
                        'manufacturer' => $item['labeler_name'] ?? null,
                        'rxcui' => $item['openfda']['rxcui'][0] ?? null,
                        'active_ingredients' => $this->formatActiveIngredients($item['active_ingredients'] ?? []),
                        'product_type' => $item['product_type'] ?? null,
                    ];
                }

                return $results;

            } catch (\Exception $e) {
                Log::error('OpenFDA search failed: '.$e->getMessage());

                return [];
            }
        });
    }

    private function formatRoute($route): string
    {
        if (is_array($route)) {
            return implode(', ', array_map('ucwords', $route));
        }

        return ucwords((string) $route);
    }

    private function extractStrength(array $item): ?string
    {
        if (! empty($item['active_ingredients'][0]['strength'])) {
            return $item['active_ingredients'][0]['strength'];
        }

        return null;
    }

    private function formatActiveIngredients(array $ingredients): array
    {
        return array_map(function ($ing) {
            return [
                'name' => $ing['name'] ?? '',
                'strength' => $ing['strength'] ?? '',
            ];
        }, $ingredients);
    }
}
