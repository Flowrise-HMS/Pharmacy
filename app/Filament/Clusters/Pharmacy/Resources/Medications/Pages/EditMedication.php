<?php

namespace Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Medications\Pages;

use Filament\Resources\Pages\EditRecord;
use Modules\Pharmacy\Classes\Services\MedicationService;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Medications\MedicationResource;

class EditMedication extends EditRecord
{
    protected static string $resource = MedicationResource::class;

    /**
     * @var array<string, mixed>
     */
    private array $formPayload = [];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return array_merge(
            $data,
            app(MedicationService::class)->billingAttributesFromService($this->record->service),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->formPayload = app(MedicationService::class)->normalizeBillingDefaults($data);

        return app(MedicationService::class)->extractMedicationAttributes($this->formPayload);
    }

    protected function afterSave(): void
    {
        app(MedicationService::class)->completeUpdate($this->record, $this->formPayload);
    }
}
