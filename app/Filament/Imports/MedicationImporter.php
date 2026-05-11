<?php

namespace Modules\Pharmacy\Filament\Imports;

use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Modules\Pharmacy\Classes\Services\MedicationService;
use Modules\Pharmacy\Models\Drug;
use Modules\Pharmacy\Models\Medication;
use Modules\Pharmacy\Models\StockItem;

class MedicationImporter extends Importer
{
    protected static ?string $model = Medication::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('service_code')
                ->rules(['max:255']),
            ImportColumn::make('price')
                ->numeric()
                ->rules(['numeric', 'min:0']),
            ImportColumn::make('insurance_price')
                ->numeric()
                ->rules(['numeric', 'min:0']),
            ImportColumn::make('is_insurance_covered')
                ->boolean()
                ->rules(['boolean']),
            ImportColumn::make('requires_prescription')
                ->boolean()
                ->rules(['boolean']),
            ImportColumn::make('initial_stock_quantity')
                ->numeric()
                ->rules(['integer', 'min:0']),
            ImportColumn::make('branch_id')
                ->rules(['max:36']),
            ImportColumn::make('rxnorm_code')
                ->rules(['max:255']),
            ImportColumn::make('ndc_code')
                ->rules(['max:255']),
            ImportColumn::make('generic_name')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('brand_name')
                ->rules(['max:255']),
            ImportColumn::make('dosage_form')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('strength')
                ->rules(['max:255']),
            ImportColumn::make('controlled_schedule')
                ->rules(['max:255']),
            ImportColumn::make('is_active')
                ->requiredMapping()
                ->boolean()
                ->rules(['required', 'boolean']),
        ];
    }

    public function resolveRecord(): ?Medication
    {
        if (isset($this->data['ndc_code'])) {
            $medication = Medication::where('ndc_code', $this->data['ndc_code'])->first();
            if ($medication) {
                return $medication;
            }
        }

        if (isset($this->data['rxnorm_code'])) {
            $medication = Medication::where('rxnorm_code', $this->data['rxnorm_code'])->first();
            if ($medication) {
                return $medication;
            }
        }

        return new Medication;
    }

    public function saveRecord(): void
    {
        $medicationService = app(MedicationService::class);
        $data = array_merge($this->record->getAttributes(), $this->data);

        if ($this->record->exists) {
            // Update existing medication
            $this->record->update(array_intersect_key($data, array_flip((new Medication)->getFillable())));

            // Update associated service
            if ($this->record->service) {
                $this->record->service->update([
                    'price' => $data['price'] ?? $this->record->service->price,
                    'insurance_price' => $data['insurance_price'] ?? $this->record->service->insurance_price,
                    'is_insurance_covered' => $data['is_insurance_covered'] ?? $this->record->service->is_insurance_covered,
                    'requires_prescription' => $data['requires_prescription'] ?? $this->record->service->requires_prescription,
                    'is_active' => $data['is_active'] ?? $this->record->service->is_active,
                ]);
            }
        } else {
            // Create new medication and service
            $this->record = $medicationService->createWithService($data);
        }

        // Handle initial stock creation if provided
        if (! empty($data['initial_stock_quantity']) && ! empty($data['branch_id'])) {
            StockItem::firstOrCreate([
                'medication_id' => $this->record->id,
                'branch_id' => $data['branch_id'],
            ], [
                'quantity_on_hand' => 0, // initial creation
                'reorder_point' => 10,
                'reorder_quantity' => 50,
            ])->increment('quantity_on_hand', (int) $data['initial_stock_quantity']);
        }

        $this->upsertDrugReference($data);
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your medication import has completed and '.Number::format($import->successful_rows).' '.str('row')->plural($import->successful_rows).' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to import.';
        }

        return $body;
    }

    protected function upsertDrugReference(array $data): void
    {
        if (blank($data['generic_name'] ?? null)) {
            return;
        }

        Drug::query()->updateOrCreate(
            [
                'source_provider' => 'local',
                'source_identifier' => $this->resolveDrugSourceIdentifier($data),
            ],
            [
                'rxnorm_code' => $data['rxnorm_code'] ?? null,
                'ndc_code' => $data['ndc_code'] ?? null,
                'generic_name' => $data['generic_name'],
                'display_name' => $data['brand_name'] ?: $data['generic_name'],
                'brand_name' => $data['brand_name'] ?? null,
                'strength_text' => $data['strength'] ?? null,
                'dosage_form_text' => $data['dosage_form'] ?? null,
                'is_cached_external' => false,
                'is_active' => (bool) ($data['is_active'] ?? true),
                'raw_payload' => $data,
            ]
        );
    }

    protected function resolveDrugSourceIdentifier(array $data): string
    {
        if (filled($data['rxnorm_code'] ?? null)) {
            return 'rxnorm:'.$data['rxnorm_code'];
        }

        if (filled($data['ndc_code'] ?? null)) {
            return 'ndc:'.$data['ndc_code'];
        }

        return 'local:'.md5(Str::lower(implode('|', [
            $data['generic_name'] ?? '',
            $data['brand_name'] ?? '',
            $data['strength'] ?? '',
            $data['dosage_form'] ?? '',
        ])));
    }
}
