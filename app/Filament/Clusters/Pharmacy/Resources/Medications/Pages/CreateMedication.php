<?php

namespace Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Medications\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Pharmacy\Classes\Services\MedicationService;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Medications\MedicationResource;
use Modules\Pharmacy\Models\Drug;
use Modules\Pharmacy\Models\Medication;

class CreateMedication extends CreateRecord
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
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->formPayload = app(MedicationService::class)->normalizeBillingDefaults($data);

        return app(MedicationService::class)->extractMedicationAttributes($this->formPayload);
    }

    protected function handleRecordCreation(array $data): Medication
    {
        $service = app(MedicationService::class);
        $payload = $this->formPayload;

        if (filled($payload['drug_id'] ?? null) && blank($payload['service_id'] ?? null)) {
            $drug = Drug::query()->find($payload['drug_id']);

            if ($drug) {
                return $service->createFromDrug($drug, $payload);
            }
        }

        if (blank($payload['service_id'] ?? null)) {
            return $service->createWithService($payload);
        }

        return Medication::query()->create($service->extractMedicationAttributes($payload));
    }

    protected function afterCreate(): void
    {
        app(MedicationService::class)->completeCreate($this->record, $this->formPayload);
    }
}
