<?php

namespace Modules\Pharmacy\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DispenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'request_item_id' => ['required', 'uuid', 'exists:request_items,id'],
            'medication_id' => ['required', 'uuid', 'exists:medications,id'],
            'dispensed_by' => ['nullable', 'integer', 'exists:users,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'unit_id' => ['nullable', 'uuid', 'exists:units,id'],
            'batch_number' => ['nullable', 'string', 'max:100'],
            'expiry_date' => ['nullable', 'date', 'after:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'dispensed_at' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'request_item_id.required' => 'Request item is required.',
            'medication_id.required' => 'Medication is required.',
            'quantity.required' => 'Quantity is required.',
            'quantity.min' => 'Quantity must be at least 1.',
            'expiry_date.after' => 'Expiry date must be in the future.',
        ];
    }
}
