<?php

namespace Modules\Pharmacy\Filament\Exports;

use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Modules\Pharmacy\Models\Medication;

class MedicationExporter extends Exporter
{
    protected static ?string $model = Medication::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id'),
            ExportColumn::make('service.name'),
            ExportColumn::make('generic_name'),
            ExportColumn::make('brand_name'),
            ExportColumn::make('dosage_form'),
            ExportColumn::make('strength'),
            ExportColumn::make('rxnorm_code'),
            ExportColumn::make('ndc_code'),
            ExportColumn::make('controlled_schedule'),
            ExportColumn::make('is_active'),
            ExportColumn::make('units_per_stock_unit'),
            ExportColumn::make('created_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your medication export has completed and '.number_format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
