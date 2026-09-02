<?php

namespace Modules\Pharmacy\Filament\Exports;

use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Modules\Pharmacy\Models\Dispense;

class DispenseExporter extends Exporter
{
    protected static ?string $model = Dispense::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id'),
            ExportColumn::make('medication.generic_name'),
            ExportColumn::make('quantity'),
            ExportColumn::make('unit.name'),
            ExportColumn::make('batch_number'),
            ExportColumn::make('expiry_date'),
            ExportColumn::make('fulfillment_type'),
            ExportColumn::make('dispensedBy.name'),
            ExportColumn::make('dispensed_at'),
            ExportColumn::make('branch.name'),
            ExportColumn::make('notes'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your dispense export has completed and '.number_format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
