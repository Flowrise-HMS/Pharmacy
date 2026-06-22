<?php

namespace Modules\Pharmacy\Filament\Imports;

use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;
use Modules\Pharmacy\Models\Drug;

class DrugImporter extends Importer
{
    protected static ?string $model = Drug::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('source_provider')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('source_identifier')
                ->rules(['max:255']),
            ImportColumn::make('rxnorm_code')
                ->rules(['max:255']),
            ImportColumn::make('ndc_code')
                ->rules(['max:255']),
            ImportColumn::make('generic_name')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('display_name')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('brand_name')
                ->rules(['max:255']),
            ImportColumn::make('strength_text')
                ->rules(['max:255']),
            ImportColumn::make('dosage_form_text')
                ->rules(['max:255']),
            ImportColumn::make('synonyms'),
            ImportColumn::make('raw_payload'),
            ImportColumn::make('search_rank')
                ->requiredMapping()
                ->numeric()
                ->rules(['required', 'integer']),
            ImportColumn::make('times_prescribed')
                ->requiredMapping()
                ->numeric()
                ->rules(['required', 'integer']),
            ImportColumn::make('times_stocked')
                ->requiredMapping()
                ->numeric()
                ->rules(['required', 'integer']),
            ImportColumn::make('is_cached_external')
                ->requiredMapping()
                ->boolean()
                ->rules(['required', 'boolean']),
            ImportColumn::make('is_active')
                ->requiredMapping()
                ->boolean()
                ->rules(['required', 'boolean']),
        ];
    }

    public function resolveRecord(): Drug
    {
        return Drug::firstOrNew([
            'ndc_code' => $this->data['ndc_code'],
        ]);
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your drug import has completed and '.Number::format($import->successful_rows).' '.str('row')->plural($import->successful_rows).' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to import.';
        }

        return $body;
    }
}
