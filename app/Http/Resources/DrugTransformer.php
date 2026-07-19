<?php

namespace Modules\Pharmacy\Http\Resources;

use Illuminate\Http\Request;
use Modules\Core\Http\Resources\ApiTransformer;
use Modules\Pharmacy\Models\Drug;

/** @property Drug $resource */
class DrugTransformer extends ApiTransformer
{
    public function toArray(Request $request): array
    {
        return $this->filterFields([
            'id' => $this->resource->id,
            'generic_name' => $this->resource->generic_name,
            'display_name' => $this->resource->display_name,
            'brand_name' => $this->resource->brand_name,
            'strength_text' => $this->resource->strength_text,
            'dosage_form_text' => $this->resource->dosage_form_text,
            'rxnorm_code' => $this->resource->rxnorm_code,
            'ndc_code' => $this->resource->ndc_code,
            'synonyms' => $this->resource->synonyms,
            'search_rank' => $this->resource->search_rank,
            'is_active' => $this->resource->is_active,
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
        ]);
    }

    protected function allowedFields(): array
    {
        return [
            'id',
            'generic_name',
            'display_name',
            'brand_name',
            'strength_text',
            'dosage_form_text',
            'rxnorm_code',
            'ndc_code',
            'synonyms',
            'search_rank',
            'is_active',
            'created_at',
            'updated_at',
        ];
    }
}
