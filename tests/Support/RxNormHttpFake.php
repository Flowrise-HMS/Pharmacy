<?php

namespace Modules\Pharmacy\Tests\Support;

use Illuminate\Support\Facades\Http;

final class RxNormHttpFake
{
    /**
     * Fake RxNorm Prescribable API responses used by RxNormService.
     */
    public static function register(string $rxcui = '12345', string $name = 'Amoxicillin 500 MG Oral Capsule'): void
    {
        Http::fake([
            'https://rxnav.nlm.nih.gov/REST/Prescribe/rxcui.json*' => Http::response([
                'idGroup' => [
                    'rxnormId' => [$rxcui],
                ],
            ]),
            "https://rxnav.nlm.nih.gov/REST/Prescribe/rxcui/{$rxcui}/allrelated.json" => Http::response([
                'allRelatedGroup' => [
                    'conceptGroup' => [],
                ],
            ]),
            "https://rxnav.nlm.nih.gov/REST/Prescribe/rxcui/{$rxcui}/properties.json" => Http::response([
                'properties' => [
                    'rxcui' => $rxcui,
                    'name' => $name,
                    'tty' => 'SCD',
                ],
            ]),
        ]);
    }
}
