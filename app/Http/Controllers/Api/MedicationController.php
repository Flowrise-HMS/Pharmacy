<?php

namespace Modules\Pharmacy\Http\Controllers\Api;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Modules\Core\Http\Controllers\Api\ResourceApiController;
use Modules\Pharmacy\Classes\Services\MedicationService;
use Modules\Pharmacy\Enums\ControlledSchedule;
use Modules\Pharmacy\Enums\DosageForm;
use Modules\Pharmacy\Models\Medication;

/**
 * @group Pharmacy
 *
 * The medication formulary (facility-wide). Each medication has a billing
 * service carrying its `price`. Deleting deactivates the medication.
 */
class MedicationController extends ResourceApiController
{
    protected string $branchScope = 'none';

    protected string $defaultSort = 'generic_name';

    protected array $with = ['service'];

    public function __construct(protected MedicationService $medications) {}

    public static function model(): string
    {
        return Medication::class;
    }

    public function fields(): array
    {
        return ['id', 'generic_name', 'brand_name', 'strength', 'dosage_form', 'controlled_schedule', 'rxnorm_code', 'ndc_code',
            'drug_id', 'service_id', 'service.price', 'is_active', 'is_formulary', 'created_at', 'updated_at'];
    }

    public function filters(): array
    {
        return ['dosage_form' => 'dosage_form', 'is_active' => 'is_active', 'is_formulary' => 'is_formulary', 'controlled_schedule' => 'controlled_schedule'];
    }

    public function searchable(): array
    {
        return ['generic_name', 'brand_name', 'rxnorm_code', 'ndc_code'];
    }

    public function sortable(): array
    {
        return ['generic_name', 'brand_name', 'created_at'];
    }

    public function storeRules(): array
    {
        return [
            'generic_name' => ['required', 'string', 'max:255'],
            'brand_name' => ['nullable', 'string', 'max:255'],
            'strength' => ['nullable', 'string', 'max:100'],
            'dosage_form' => ['required', Rule::enum(DosageForm::class)],
            'controlled_schedule' => ['nullable', Rule::enum(ControlledSchedule::class)],
            'rxnorm_code' => ['nullable', 'string', 'max:50'],
            'ndc_code' => ['nullable', 'string', 'max:50'],
            'drug_id' => ['nullable', 'uuid', 'exists:drugs,id'],
            'is_active' => ['nullable', 'boolean'],
            'is_formulary' => ['nullable', 'boolean'],
            'price' => ['required', 'numeric', 'min:0'],
        ];
    }

    protected function createRecord(array $data): Model
    {
        return $this->medications->completeCreate($this->medications->createWithService($data), $data);
    }

    protected function updateRecord(Model $record, array $data): Model
    {
        $record->update($this->medications->extractMedicationAttributes($data));

        return array_key_exists('price', $data) ? $this->medications->completeUpdate($record, $data) : $record;
    }

    protected function deleteRecord(Model $record): void
    {
        $record->update(['is_active' => false]);
    }
}
