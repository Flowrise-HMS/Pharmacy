<?php

namespace Modules\Pharmacy\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Pharmacy\Enums\ControlledSchedule;
use Modules\Pharmacy\Enums\DosageForm;

class MedicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $medicationId = $this->route('medication')?->id;

        return [
            'service_id' => ['nullable', 'uuid', 'exists:services,id'],
            'rxnorm_code' => ['nullable', 'string', 'max:50'],
            'ndc_code' => ['nullable', 'string', 'max:50', Rule::unique('medications', 'ndc_code')->ignore($medicationId)],
            'generic_name' => ['required', 'string', 'max:255'],
            'brand_name' => ['nullable', 'string', 'max:255'],
            'dosage_form' => ['nullable', Rule::enum(DosageForm::class)],
            'strength' => ['nullable', 'string', 'max:100'],
            'controlled_schedule' => ['nullable', Rule::enum(ControlledSchedule::class)],
            'is_active' => ['nullable', 'boolean'],
            'stock_unit_id' => ['nullable', 'uuid', 'exists:units,id'],
            'billing_unit_id' => ['nullable', 'uuid', 'exists:units,id'],
            'dose_unit_id' => ['nullable', 'uuid', 'exists:units,id'],
            'units_per_stock_unit' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'generic_name.required' => 'Generic name is required.',
            'ndc_code.unique' => 'This NDC code is already registered.',
        ];
    }
}
