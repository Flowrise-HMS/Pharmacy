<?php

namespace Modules\Pharmacy\Http\Resources;

use Illuminate\Http\Request;
use Modules\Core\Http\Resources\ApiTransformer;
use Modules\Pharmacy\Models\Dispense;

/** @property Dispense $resource */
class DispenseTransformer extends ApiTransformer
{
    public function toArray(Request $request): array
    {
        return $this->filterFields([
            'id' => $this->resource->id,
            'branch_id' => $this->resource->branch_id,
            'request_item_id' => $this->resource->request_item_id,
            'medication_id' => $this->resource->medication_id,
            'dispensed_by' => $this->resource->dispensed_by,
            'quantity' => $this->resource->quantity,
            'batch_number' => $this->resource->batch_number,
            'expiry_date' => $this->resource->expiry_date?->format('Y-m-d'),
            'notes' => $this->resource->notes,
            'dispensed_at' => $this->resource->dispensed_at?->toIso8601String(),
            'fulfillment_type' => $this->resource->fulfillment_type?->value,
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
        ]);
    }

    protected function allowedFields(): array
    {
        return [
            'id',
            'branch_id',
            'request_item_id',
            'medication_id',
            'dispensed_by',
            'quantity',
            'batch_number',
            'expiry_date',
            'notes',
            'dispensed_at',
            'fulfillment_type',
            'created_at',
            'updated_at',
        ];
    }
}
