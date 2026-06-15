<?php

namespace Modules\Pharmacy\Filament\Concerns;

use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Modules\Clinical\Filament\Support\MarRecordDoseFormSchema;
use Modules\Clinical\Models\RequestItem;
use Modules\Pharmacy\Classes\Services\DispenseService;

trait HandlesPosPrescriptionFulfillmentActions
{
    protected function dispensePrescriptionTableAction(): Action
    {
        return Action::make('dispensePrescription')
            ->label(__('Dispense from stock'))
            ->tooltip(__('Give medication from hospital pharmacy inventory'))
            ->icon(Heroicon::OutlinedBeaker)
            ->color('success')
            ->modalHeading(__('Dispense prescribed medication'))
            ->modalSubmitActionLabel(__('Dispense'))
            ->schema(fn (RequestItem $record): array => MarRecordDoseFormSchema::dispenseFields($record))
            ->action(function (RequestItem $record, array $data): void {
                try {
                    app(DispenseService::class)->dispense($record, $data, Auth::user());
                    Notification::make()->title(__('Dispensed successfully'))->success()->send();
                    $this->afterPrescriptionFulfillment();
                } catch (\Throwable $e) {
                    Notification::make()->title(__('Dispense failed'))->body($e->getMessage())->danger()->persistent()->send();
                }
            });
    }

    protected function recordOutsidePurchaseTableAction(): Action
    {
        return Action::make('recordOutsidePurchase')
            ->label(__('Patient buys elsewhere'))
            ->tooltip(__('Not in our stock — print a prescription slip for the patient to fill at another pharmacy'))
            ->icon(Heroicon::OutlinedDocumentText)
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading(__('Patient will obtain medication elsewhere'))
            ->modalDescription(function (RequestItem $record): ?string {
                if ($record->prescriptionDetail?->isInFacility()) {
                    return __('We cannot supply this from hospital stock. A prescription slip will be printed for the patient. This order will be marked complete and ward MAR will stop for this medication.');
                }

                return __('We do not have this medication in stock. A prescription slip will be printed so the patient can buy it at another pharmacy. This order will be marked complete without dispensing from our inventory.');
            })
            ->modalSubmitActionLabel(__('Print prescription slip'))
            ->schema([
                Textarea::make('notes')
                    ->label(__('Notes for the slip'))
                    ->helperText(__('Optional instructions for the patient or external pharmacist'))
                    ->rows(2),
            ])
            ->action(function (RequestItem $record, array $data): void {
                try {
                    app(DispenseService::class)->recordOutsidePurchase(
                        $record,
                        Auth::user(),
                        $data['notes'] ?? null,
                    );

                    Notification::make()
                        ->title(__('Prescription slip ready'))
                        ->body(__('The patient should obtain this medication at an external pharmacy.'))
                        ->success()
                        ->send();
                    $this->afterPrescriptionFulfillment();

                    $slipUrl = route('pharmacy.prescription-slip', [
                        'requestItem' => $record->id,
                        'autoprint' => 1,
                    ]);
                    $this->dispatch('pos-open-receipt', url: $slipUrl);
                } catch (\Throwable $e) {
                    Notification::make()
                        ->title(__('Could not print external prescription'))
                        ->body($e->getMessage())
                        ->danger()
                        ->persistent()
                        ->send();
                }
            });
    }

    protected function reprintPrescriptionSlipTableAction(): Action
    {
        return Action::make('reprintPrescriptionSlip')
            ->label(__('Reprint slip'))
            ->tooltip(__('Print another copy of the external pharmacy prescription slip'))
            ->icon(Heroicon::OutlinedPrinter)
            ->color('gray')
            ->action(function (RequestItem $record): void {
                $url = route('pharmacy.prescription-slip', [
                    'requestItem' => $record->id,
                    'autoprint' => 1,
                ]);
                $this->dispatch('pos-open-receipt', url: $url);
            });
    }

    protected function afterPrescriptionFulfillment(): void
    {
        if (method_exists($this, 'resetTable')) {
            $this->resetTable();
        }

        if (property_exists($this, 'selectedPatientId')) {
            unset($this->patientPrescriptions);
        }
    }
}
