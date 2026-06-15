<?php

namespace Modules\Pharmacy\Livewire;

use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Modules\Billing\Enums\InvoiceLineStatus;
use Modules\Clinical\Models\RequestItem;
use Modules\Pharmacy\Classes\Services\PharmacyPosPrescriptionService;
use Modules\Pharmacy\Filament\Concerns\HandlesPosPrescriptionFulfillmentActions;

class PatientPrescriptionsTable extends Component implements HasActions, HasSchemas, HasTable
{
    use HandlesPosPrescriptionFulfillmentActions;
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    public ?string $patientId = null;

    public ?string $branchId = null;

    public function table(Table $table): Table
    {
        $service = app(PharmacyPosPrescriptionService::class);

        return $table
            ->query(fn (): Builder => $this->prescriptionsQuery())
            ->heading(__('Patient prescriptions'))
            ->description(__('Medication orders for this patient. Dispense from hospital stock when available; if out of stock, use “Patient buys elsewhere” to print a slip for an external pharmacy.'))
            ->defaultSort('created_at', 'desc')
            ->paginated(false)
            ->columns([
                TextColumn::make('service.name')
                    ->label(__('Medication'))
                    ->searchable()
                    ->wrap()
                    ->weight('medium'),
                TextColumn::make('sig_summary')
                    ->label(__('SIG'))
                    ->wrap()
                    ->getStateUsing(fn (RequestItem $record): string => $service->lineFor($record, $this->branchId)->sigSummary),
                TextColumn::make('prescriptionDetail.administration_context')
                    ->label(__('Context'))
                    ->badge(),
                TextColumn::make('stock_label')
                    ->label(__('Stock'))
                    ->badge()
                    ->getStateUsing(fn (RequestItem $record): string => $service->lineFor($record, $this->branchId)->stockLabel)
                    ->color(fn (RequestItem $record): string => match (true) {
                        $service->lineFor($record, $this->branchId)->stockQuantity > 0 => 'success',
                        ! $service->lineFor($record, $this->branchId)->hasMedicationCatalogRow => 'gray',
                        default => 'danger',
                    }),
                TextColumn::make('payment_status')
                    ->label(__('Payment'))
                    ->badge()
                    ->getStateUsing(fn (RequestItem $record): string => $record->payment_status?->getLabel() ?? __('Not billed'))
                    ->color(fn (RequestItem $record): string => match ($record->payment_status) {
                        InvoiceLineStatus::Paid => 'success',
                        InvoiceLineStatus::Unpaid => 'warning',
                        InvoiceLineStatus::Partial => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('fulfillment_status')
                    ->label(__('Status'))
                    ->wrap()
                    ->badge()
                    ->getStateUsing(function (RequestItem $record) use ($service): string {
                        $line = $service->lineFor($record, $this->branchId);

                        if ($line->canReprintExternalSlip) {
                            return __('External slip issued');
                        }

                        if ($line->canDispenseInHouse || $line->canRecordOutsidePurchase) {
                            return __('Ready to fulfill');
                        }

                        return $line->blockedReason ?? __('Unavailable');
                    })
                    ->color(function (RequestItem $record) use ($service): string {
                        $line = $service->lineFor($record, $this->branchId);

                        if ($line->canReprintExternalSlip) {
                            return 'info';
                        }

                        if ($line->canDispenseInHouse || $line->canRecordOutsidePurchase) {
                            return 'success';
                        }

                        return 'danger';
                    }),
            ])
            ->recordActions([
                $this->dispensePrescriptionTableAction()
                    ->visible(fn (RequestItem $record): bool => $service->lineFor($record, $this->branchId)->canDispenseInHouse),
                $this->recordOutsidePurchaseTableAction()
                    ->visible(fn (RequestItem $record): bool => $service->lineFor($record, $this->branchId)->canRecordOutsidePurchase),
                $this->reprintPrescriptionSlipTableAction()
                    ->visible(fn (RequestItem $record): bool => $service->lineFor($record, $this->branchId)->canReprintExternalSlip),
            ])
            ->emptyStateHeading(__('No prescriptions'))
            ->emptyStateDescription(__('This patient has no active or recently issued external prescription slips at this branch.'))
            ->emptyStateIcon(Heroicon::OutlinedClipboardDocumentList);
    }

    protected function prescriptionsQuery(): Builder
    {
        if (blank($this->patientId) || blank($this->branchId)) {
            return RequestItem::query()->whereRaw('0 = 1');
        }

        return RequestItem::query()
            ->whereHas('prescriptionDetail')
            ->whereHas('serviceRequest', fn (Builder $q) => $q
                ->where('patient_id', $this->patientId)
                ->where('branch_id', $this->branchId))
            ->where(function (Builder $q) {
                $q->whereIn('status', ['pending', 'in_progress'])
                    ->orWhereHas('dispenses', fn (Builder $d) => $d
                        ->where('fulfillment_type', \Modules\Pharmacy\Enums\DispenseFulfillmentType::OUTSIDE_PURCHASE->value));
            })
            ->with([
                'service',
                'prescriptionDetail.doseUnit',
                'invoiceLine',
                'serviceRequest.orderedBy',
                'serviceRequest.encounter',
                'serviceRequest.patient',
                'dispenses',
            ])
            ->latest();
    }

    public function render(): View
    {
        return view('pharmacy::livewire.patient-prescriptions-table');
    }
}
