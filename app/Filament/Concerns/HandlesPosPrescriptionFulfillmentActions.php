<?php

namespace Modules\Pharmacy\Filament\Concerns;

use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Modules\Clinical\Filament\Support\MarRecordDoseFormSchema;
use Modules\Clinical\Models\RequestItem;
use Modules\Pharmacy\Classes\Services\DispenseService;
use Modules\Pharmacy\Classes\Services\PharmacyPosPrescriptionService;

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
        $service = app(PharmacyPosPrescriptionService::class);

        return Action::make('recordOutsidePurchase')
            ->label(__('Patient buys elsewhere'))
            ->tooltip(__('Not in our stock — print a prescription slip for the patient to fill at another pharmacy'))
            ->icon(Heroicon::OutlinedDocumentText)
            ->color('warning')
            ->modalHeading(__('Patient will obtain medication elsewhere'))
            ->modalDescription(function (RequestItem $record): ?string {
                if ($record->prescriptionDetail?->isInFacility()) {
                    return __('We cannot supply this from hospital stock. A prescription slip will be printed for the patient. This order will be marked complete and ward MAR will stop for this medication.');
                }

                return __('We do not have this medication in stock. A prescription slip will be printed so the patient can buy it at another pharmacy. This order will be marked complete without dispensing from our inventory.');
            })
            ->modalSubmitActionLabel(__('Print prescription slip'))
            ->schema(function (RequestItem $record) use ($service): array {
                $eligibleCount = $this->posPrescriptionEligibleOutsideCount($service);
                $fields = [];

                if ($eligibleCount > 1) {
                    $fields[] = Radio::make('issue_scope')
                        ->label(__('Print scope'))
                        ->options([
                            'all' => __('Issue all :count medications on one slip', ['count' => $eligibleCount]),
                            'this' => __('This medication only'),
                        ])
                        ->default('all')
                        ->required();
                }

                $fields[] = Textarea::make('notes')
                    ->label(__('Notes for the slip'))
                    ->helperText(__('Optional instructions for the patient or external pharmacist'))
                    ->rows(2);

                return $fields;
            })
            ->action(function (RequestItem $record, array $data) use ($service): void {
                $eligible = $service->eligibleForOutsidePurchase(
                    $this->posPrescriptionPatientId(),
                    $this->posPrescriptionBranchId(),
                );

                $items = ($data['issue_scope'] ?? 'this') === 'all' && $eligible->count() > 1
                    ? $eligible
                    : collect([$record]);

                $this->issueOutsidePurchaseAndPrint($items, $data['notes'] ?? null);
            });
    }

    protected function reprintPrescriptionSlipTableAction(): Action
    {
        return Action::make('reprintPrescriptionSlip')
            ->label(__('Reprint slip'))
            ->tooltip(__('Print another copy of the combined external pharmacy prescription slip'))
            ->icon(Heroicon::OutlinedPrinter)
            ->color('gray')
            ->action(function (): void {
                $items = app(PharmacyPosPrescriptionService::class)
                    ->eligibleForCombinedReprint(
                        $this->posPrescriptionPatientId(),
                        $this->posPrescriptionBranchId(),
                    );

                if ($items->isEmpty()) {
                    Notification::make()
                        ->title(__('No external slips to reprint'))
                        ->warning()
                        ->send();

                    return;
                }

                $this->openCombinedPrescriptionSlip($items->pluck('id')->all());
            });
    }

    protected function issueAllOutsidePurchaseHeaderAction(): Action
    {
        $service = app(PharmacyPosPrescriptionService::class);

        return Action::make('issueAllOutsidePurchase')
            ->label(function () use ($service): string {
                $count = $service->eligibleForOutsidePurchase(
                    $this->posPrescriptionPatientId(),
                    $this->posPrescriptionBranchId(),
                )->count();

                return __('Issue all & print one slip (:count)', ['count' => $count]);
            })
            ->icon(Heroicon::OutlinedDocumentText)
            ->color('warning')
            ->visible(fn (): bool => $service->eligibleForOutsidePurchase(
                $this->posPrescriptionPatientId(),
                $this->posPrescriptionBranchId(),
            )->isNotEmpty())
            ->modalHeading(__('Issue external prescriptions on one slip'))
            ->modalDescription(__('All out-of-stock medications for this patient will be marked complete and printed on a single receipt for an external pharmacy.'))
            ->modalSubmitActionLabel(__('Print combined slip'))
            ->schema([
                Textarea::make('notes')
                    ->label(__('Notes for the slip'))
                    ->helperText(__('Optional instructions for the patient or external pharmacist'))
                    ->rows(2),
            ])
            ->action(function (array $data) use ($service): void {
                $items = $service->eligibleForOutsidePurchase(
                    $this->posPrescriptionPatientId(),
                    $this->posPrescriptionBranchId(),
                );

                $this->issueOutsidePurchaseAndPrint($items, $data['notes'] ?? null);
            });
    }

    protected function reprintCombinedSlipHeaderAction(): Action
    {
        $service = app(PharmacyPosPrescriptionService::class);

        return Action::make('reprintCombinedSlip')
            ->label(function () use ($service): string {
                $count = $service->eligibleForCombinedReprint(
                    $this->posPrescriptionPatientId(),
                    $this->posPrescriptionBranchId(),
                )->count();

                return __('Reprint combined slip (:count)', ['count' => $count]);
            })
            ->icon(Heroicon::OutlinedPrinter)
            ->color('gray')
            ->visible(fn (): bool => $service->eligibleForCombinedReprint(
                $this->posPrescriptionPatientId(),
                $this->posPrescriptionBranchId(),
            )->isNotEmpty())
            ->action(function () use ($service): void {
                $ids = $service->eligibleForCombinedReprint(
                    $this->posPrescriptionPatientId(),
                    $this->posPrescriptionBranchId(),
                )->pluck('id')->all();

                $this->openCombinedPrescriptionSlip($ids);
            });
    }

    protected function recordOutsidePurchaseBulkAction(): BulkAction
    {
        $service = app(PharmacyPosPrescriptionService::class);

        return BulkAction::make('recordOutsidePurchaseBulk')
            ->label(__('Patient buys elsewhere'))
            ->icon(Heroicon::OutlinedDocumentText)
            ->color('warning')
            ->modalHeading(__('Patient will obtain selected medications elsewhere'))
            ->modalDescription(__('Selected out-of-stock medications will be marked complete and printed on one combined slip.'))
            ->modalSubmitActionLabel(__('Print combined slip'))
            ->schema([
                Textarea::make('notes')
                    ->label(__('Notes for the slip'))
                    ->helperText(__('Optional instructions for the patient or external pharmacist'))
                    ->rows(2),
            ])
            ->action(function (Collection $records, array $data) use ($service): void {
                $eligible = $records
                    ->filter(fn (RequestItem $record): bool => $service->lineFor($record, $this->posPrescriptionBranchId())->canRecordOutsidePurchase)
                    ->values();

                if ($eligible->isEmpty()) {
                    Notification::make()
                        ->title(__('No eligible prescriptions selected'))
                        ->body(__('Only out-of-stock medications that are ready for external fulfillment can be included.'))
                        ->warning()
                        ->send();

                    return;
                }

                $this->issueOutsidePurchaseAndPrint($eligible, $data['notes'] ?? null);
            })
            ->deselectRecordsAfterCompletion();
    }

    protected function reprintCombinedSlipBulkAction(): BulkAction
    {
        $service = app(PharmacyPosPrescriptionService::class);

        return BulkAction::make('reprintCombinedSlipBulk')
            ->label(__('Reprint combined slip'))
            ->icon(Heroicon::OutlinedPrinter)
            ->color('gray')
            ->action(function (Collection $records) use ($service): void {
                $eligible = $records
                    ->filter(fn (RequestItem $record): bool => $service->lineFor($record, $this->posPrescriptionBranchId())->canReprintExternalSlip)
                    ->values();

                if ($eligible->isEmpty()) {
                    Notification::make()
                        ->title(__('No external slips to reprint'))
                        ->warning()
                        ->send();

                    return;
                }

                $this->openCombinedPrescriptionSlip($eligible->pluck('id')->all());
            })
            ->deselectRecordsAfterCompletion();
    }

    /**
     * @param  Collection<int, RequestItem>|array<int, RequestItem>  $items
     */
    protected function issueOutsidePurchaseAndPrint(Collection | array $items, ?string $notes = null): void
    {
        $items = $items instanceof Collection ? $items : collect($items);

        if ($items->isEmpty()) {
            return;
        }

        try {
            app(DispenseService::class)->recordOutsidePurchaseBatch($items, Auth::user(), $notes);

            $count = $items->count();
            Notification::make()
                ->title(__('Prescription slip ready'))
                ->body(trans_choice(
                    '{1} The patient should obtain this medication at an external pharmacy.|[2,*] :count medications were included on one combined slip for an external pharmacy.',
                    $count,
                    ['count' => $count],
                ))
                ->success()
                ->send();

            $this->afterPrescriptionFulfillment();
            $this->openCombinedPrescriptionSlip($items->pluck('id')->all());
        } catch (\Throwable $e) {
            Notification::make()
                ->title(__('Could not print external prescription'))
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();
        }
    }

    /**
     * @param  array<int, string>  $itemIds
     */
    protected function openCombinedPrescriptionSlip(array $itemIds): void
    {
        if ($itemIds === []) {
            return;
        }

        $this->dispatch('pos-open-receipt', url: $this->combinedPrescriptionSlipUrl($itemIds));
    }

    /**
     * @param  array<int, string>  $itemIds
     */
    protected function combinedPrescriptionSlipUrl(array $itemIds): string
    {
        return route('pharmacy.prescription-slip.combined', [
            'items' => array_values($itemIds),
            'autoprint' => 1,
        ]);
    }

    protected function posPrescriptionPatientId(): string
    {
        return (string) ($this->patientId ?? '');
    }

    protected function posPrescriptionBranchId(): string
    {
        return (string) ($this->branchId ?? '');
    }

    protected function posPrescriptionEligibleOutsideCount(PharmacyPosPrescriptionService $service): int
    {
        return $service->eligibleForOutsidePurchase(
            $this->posPrescriptionPatientId(),
            $this->posPrescriptionBranchId(),
        )->count();
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
