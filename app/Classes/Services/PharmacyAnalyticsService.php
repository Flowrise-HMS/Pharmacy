<?php

namespace Modules\Pharmacy\Classes\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Billing\Enums\InvoiceLineStatus;
use Modules\Billing\Models\InvoiceLine;
use Modules\Clinical\Enums\MedicationAdministrationStatus;
use Modules\Clinical\Enums\RequestItemStatus;
use Modules\Clinical\Models\MedicationAdministration;
use Modules\Clinical\Models\RequestItem;
use Modules\Core\Models\Branch;
use Modules\Pharmacy\Data\PharmacyReportCriteria;
use Modules\Pharmacy\Enums\AdministrationContext;
use Modules\Pharmacy\Enums\DispenseFulfillmentType;
use Modules\Pharmacy\Models\Dispense;
use Modules\Pharmacy\Models\Medication;
use Modules\Pharmacy\Models\PrescriptionDetail;
use Modules\Pharmacy\Models\StockItem;
use Modules\Pharmacy\Models\StockMovement;

class PharmacyAnalyticsService
{
    /**
     * @return array<string, mixed>
     */
    public function buildFromCriteria(PharmacyReportCriteria $criteria): array
    {
        return [
            'summary' => $this->getRevenueSummary($criteria) + [
                'dispenses' => $this->countDispensesInRange($criteria),
                'low_stock_count' => $this->countLowStock($criteria->branchId),
            ],
            'revenue_mix' => $this->getRevenueMix($criteria),
            'daily_sales_trend' => $this->getDailySalesTrend($criteria),
            'sales_by_branch' => $this->getSalesByBranch($criteria),
            'dispenses_trend' => $this->getDispensesTrend($criteria),
            'fulfillment_type_split' => $this->getFulfillmentTypeSplit($criteria),
            'top_dispensed_medications' => $this->getTopDispensedMedications($criteria, 10),
            'top_selling_medications' => $this->getTopSellingMedications($criteria, 10),
            'top_selling_services' => $this->getTopSellingServices($criteria, 10),
            'prescription_status' => $this->getPrescriptionStatusBreakdown($criteria->branchId),
            'administration_context_split' => $this->getAdministrationContextSplit($criteria->branchId),
            'mar_adherence' => $this->getMarAdherenceSummary($criteria),
            'stock_movement_by_reason' => $this->getStockMovementByReason($criteria),
            'low_stock_items' => $this->getLowStockItems($criteria->branchId),
            'recent_dispenses' => $this->getRecentDispenses($criteria, 25),
            'recent_pos_sales' => $this->getRecentPosSales($criteria, 25),
            'outside_purchase_dispenses' => $this->getOutsidePurchaseDispenses($criteria, 25),
        ];
    }

    /**
     * @return array{
     *     dispenses_today: int,
     *     queue_pending: int,
     *     queue_in_progress: int,
     *     low_stock_count: int,
     *     medication_sales_today: string,
     *     service_sales_today: string
     * }
     */
    public function getOperationsSummary(?string $branchId = null): array
    {
        $todayCriteria = PharmacyReportCriteria::fromRequest([
            'preset' => 'today',
            'branch_id' => $branchId,
        ]);

        $revenue = $this->getRevenueSummary($todayCriteria);

        return [
            'dispenses_today' => Dispense::query()
                ->whereDate('dispensed_at', today())
                ->when($branchId, fn (Builder $q) => $this->scopeDispensesByBranch($q, $branchId))
                ->count(),
            'queue_pending' => $this->pharmacyRequestItemsQuery($branchId)
                ->where('status', RequestItemStatus::PENDING->value)
                ->count(),
            'queue_in_progress' => $this->pharmacyRequestItemsQuery($branchId)
                ->where('status', RequestItemStatus::IN_PROGRESS->value)
                ->count(),
            'low_stock_count' => $this->countLowStock($branchId),
            'medication_sales_today' => $revenue['medication_sales'],
            'service_sales_today' => $revenue['service_sales'],
        ];
    }

    /**
     * @return array{
     *     total_sales: string,
     *     medication_sales: string,
     *     service_sales: string,
     *     transaction_count: int,
     *     avg_ticket: string|null
     * }
     */
    public function getRevenueSummary(PharmacyReportCriteria $criteria): array
    {
        $medicationSales = $this->sumLineRevenue($this->medicationRevenueLinesQuery($criteria));
        $serviceSales = $this->sumLineRevenue($this->serviceRevenueLinesQuery($criteria));

        if ($criteria->lineKind === 'medication') {
            $serviceSales = '0.00';
        } elseif ($criteria->lineKind === 'service') {
            $medicationSales = '0.00';
        }

        $totalSales = $this->normalizeMoney(bcadd($medicationSales, $serviceSales, 2));

        $transactionQuery = $this->revenueLinesQuery($criteria);
        $transactionCount = (int) (clone $transactionQuery)
            ->select('invoice_id')
            ->distinct()
            ->count('invoice_id');

        $avgTicket = $transactionCount > 0
            ? $this->normalizeMoney(bcdiv($totalSales, (string) $transactionCount, 2))
            : null;

        return [
            'total_sales' => $totalSales,
            'medication_sales' => $this->normalizeMoney($medicationSales),
            'service_sales' => $this->normalizeMoney($serviceSales),
            'transaction_count' => $transactionCount,
            'avg_ticket' => $avgTicket,
        ];
    }

    /**
     * @return array{labels: list<string>, amounts: list<string>}
     */
    public function getRevenueMix(PharmacyReportCriteria $criteria): array
    {
        $summary = $this->getRevenueSummary($criteria);

        return [
            'labels' => [__('Medications'), __('Services')],
            'amounts' => [$summary['medication_sales'], $summary['service_sales']],
        ];
    }

    /**
     * @return array{labels: list<string>, medication_amounts: list<float>, service_amounts: list<float>}
     */
    public function getDailySalesTrend(PharmacyReportCriteria $criteria): array
    {
        $labels = [];
        $medicationAmounts = [];
        $serviceAmounts = [];

        $cursor = $criteria->startDate->copy()->startOfDay();
        $end = $criteria->endDate->copy()->endOfDay();

        while ($cursor->lte($end)) {
            $dayStart = $cursor->copy()->startOfDay();
            $dayEnd = $cursor->copy()->endOfDay();
            $labels[] = $cursor->format('M j');

            $dayCriteria = new PharmacyReportCriteria(
                startDate: $dayStart,
                endDate: $dayEnd,
                branchId: $criteria->branchId,
                lineKind: $criteria->lineKind,
                channel: $criteria->channel,
            );

            $medicationAmounts[] = (float) $this->sumLineRevenue($this->medicationRevenueLinesQuery($dayCriteria));
            $serviceAmounts[] = (float) $this->sumLineRevenue($this->serviceRevenueLinesQuery($dayCriteria));

            $cursor->addDay();
        }

        if ($criteria->lineKind === 'medication') {
            $serviceAmounts = array_fill(0, count($labels), 0.0);
        } elseif ($criteria->lineKind === 'service') {
            $medicationAmounts = array_fill(0, count($labels), 0.0);
        }

        return [
            'labels' => $labels,
            'medication_amounts' => $medicationAmounts,
            'service_amounts' => $serviceAmounts,
        ];
    }

    /**
     * @return array{labels: list<string>, amounts: list<float>}
     */
    public function getMonthlySalesTrend(int $months = 12, ?string $branchId = null, string $lineKind = 'all'): array
    {
        $labels = [];
        $amounts = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $labels[] = $month->format('M Y');

            $criteria = new PharmacyReportCriteria(
                startDate: $month->copy()->startOfMonth(),
                endDate: $month->copy()->endOfMonth(),
                branchId: $branchId,
                lineKind: $lineKind,
            );

            if ($lineKind === 'service') {
                $amounts[] = (float) $this->sumLineRevenue($this->serviceRevenueLinesQuery($criteria));
            } elseif ($lineKind === 'medication') {
                $amounts[] = (float) $this->sumLineRevenue($this->medicationRevenueLinesQuery($criteria));
            } else {
                $med = $this->sumLineRevenue($this->medicationRevenueLinesQuery($criteria));
                $svc = $this->sumLineRevenue($this->serviceRevenueLinesQuery($criteria));
                $amounts[] = (float) bcadd($med, $svc, 2);
            }
        }

        return ['labels' => $labels, 'amounts' => $amounts];
    }

    /**
     * @return list<array{branch_name: string, medication_amount: string, service_amount: string}>
     */
    public function getSalesByBranch(PharmacyReportCriteria $criteria): array
    {
        $branches = Branch::query()
            ->when($criteria->branchId, fn (Builder $q) => $q->where('id', $criteria->branchId))
            ->orderBy('name')
            ->get(['id', 'name']);

        $rows = [];

        foreach ($branches as $branch) {
            $branchCriteria = new PharmacyReportCriteria(
                startDate: $criteria->startDate,
                endDate: $criteria->endDate,
                branchId: (string) $branch->id,
                lineKind: $criteria->lineKind,
                channel: $criteria->channel,
            );

            $summary = $this->getRevenueSummary($branchCriteria);

            $rows[] = [
                'branch_name' => (string) $branch->name,
                'medication_amount' => $summary['medication_sales'],
                'service_amount' => $summary['service_sales'],
            ];
        }

        return $rows;
    }

    /**
     * @return array{labels: list<string>, counts: list<int>}
     */
    public function getDispensesTrend(PharmacyReportCriteria $criteria): array
    {
        $labels = [];
        $counts = [];

        $cursor = $criteria->startDate->copy()->startOfDay();
        $end = $criteria->endDate->copy()->endOfDay();

        while ($cursor->lte($end)) {
            $labels[] = $cursor->format('M j');
            $counts[] = Dispense::query()
                ->whereBetween('dispensed_at', [$cursor->copy()->startOfDay(), $cursor->copy()->endOfDay()])
                ->when($criteria->branchId, fn (Builder $q) => $this->scopeDispensesByBranch($q, $criteria->branchId))
                ->count();
            $cursor->addDay();
        }

        return ['labels' => $labels, 'counts' => $counts];
    }

    /**
     * @return array{labels: list<string>, counts: list<int>}
     */
    public function getFulfillmentTypeSplit(PharmacyReportCriteria $criteria): array
    {
        $rows = Dispense::query()
            ->select('fulfillment_type', DB::raw('count(*) as total'))
            ->whereBetween('dispensed_at', [$criteria->startDate->copy()->startOfDay(), $criteria->endDate->copy()->endOfDay()])
            ->when($criteria->branchId, fn (Builder $q) => $this->scopeDispensesByBranch($q, $criteria->branchId))
            ->groupBy('fulfillment_type')
            ->get();

        $labels = [];
        $counts = [];

        foreach ($rows as $row) {
            $type = $row->fulfillment_type;
            $labels[] = $type instanceof DispenseFulfillmentType
                ? (string) $type->getLabel()
                : (string) $type;
            $counts[] = (int) $row->total;
        }

        return ['labels' => $labels, 'counts' => $counts];
    }

    /**
     * @return array{labels: list<string>, quantities: list<int>}
     */
    public function getTopDispensedMedications(PharmacyReportCriteria $criteria, int $limit = 10): array
    {
        $rows = Dispense::query()
            ->select('medication_id', DB::raw('sum(quantity) as total_qty'))
            ->with('medication.service')
            ->whereBetween('dispensed_at', [$criteria->startDate->copy()->startOfDay(), $criteria->endDate->copy()->endOfDay()])
            ->when($criteria->branchId, fn (Builder $q) => $this->scopeDispensesByBranch($q, $criteria->branchId))
            ->groupBy('medication_id')
            ->orderByDesc('total_qty')
            ->limit($limit)
            ->get();

        return [
            'labels' => $rows->map(fn (Dispense $row) => $row->medication?->displayName() ?? __('Unknown'))->all(),
            'quantities' => $rows->pluck('total_qty')->map(fn ($qty) => (int) $qty)->all(),
        ];
    }

    /**
     * @return list<array{label: string, quantity: int, revenue: string}>
     */
    public function getTopSellingMedications(PharmacyReportCriteria $criteria, int $limit = 10): array
    {
        $rows = $this->medicationRevenueLinesQuery($criteria)
            ->select(
                'service_id',
                DB::raw('sum(quantity) as total_qty'),
                DB::raw('sum(line_total) as total_revenue')
            )
            ->groupBy('service_id')
            ->orderByDesc('total_revenue')
            ->limit($limit)
            ->get();

        return $rows->map(function ($row): array {
            $service = \Modules\Core\Models\Service::query()->find($row->service_id);

            return [
                'label' => $service?->name ?? __('Unknown'),
                'quantity' => (int) $row->total_qty,
                'revenue' => $this->normalizeMoney((string) $row->total_revenue),
            ];
        })->all();
    }

    /**
     * @return list<array{label: string, quantity: int, revenue: string}>
     */
    public function getTopSellingServices(PharmacyReportCriteria $criteria, int $limit = 10): array
    {
        $rows = $this->serviceRevenueLinesQuery($criteria)
            ->select(
                'service_id',
                DB::raw('sum(quantity) as total_qty'),
                DB::raw('sum(line_total) as total_revenue')
            )
            ->groupBy('service_id')
            ->orderByDesc('total_revenue')
            ->limit($limit)
            ->get();

        return $rows->map(function ($row): array {
            $service = \Modules\Core\Models\Service::query()->find($row->service_id);

            return [
                'label' => $service?->name ?? __('Unknown'),
                'quantity' => (int) $row->total_qty,
                'revenue' => $this->normalizeMoney((string) $row->total_revenue),
            ];
        })->all();
    }

    /**
     * @return array{labels: list<string>, counts: list<int>}
     */
    public function getPrescriptionStatusBreakdown(?string $branchId = null): array
    {
        $rows = $this->pharmacyRequestItemsQuery($branchId)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->get();

        $labels = [];
        $counts = [];

        foreach ($rows as $row) {
            $status = $row->status;
            $labels[] = $status instanceof RequestItemStatus
                ? (string) $status->getLabel()
                : (string) $status;
            $counts[] = (int) $row->total;
        }

        return ['labels' => $labels, 'counts' => $counts];
    }

    /**
     * @return array{labels: list<string>, counts: list<int>}
     */
    public function getAdministrationContextSplit(?string $branchId = null): array
    {
        $query = PrescriptionDetail::query()
            ->select('administration_context', DB::raw('count(*) as total'))
            ->whereHas('requestItem', function (Builder $q) use ($branchId): void {
                $q->whereHas('prescriptionDetail');
                if ($branchId) {
                    $q->whereHas('serviceRequest', fn (Builder $sr) => $sr->where('branch_id', $branchId));
                }
            })
            ->groupBy('administration_context')
            ->get();

        $labels = [];
        $counts = [];

        foreach ($query as $row) {
            $context = $row->administration_context;
            $labels[] = $context instanceof AdministrationContext
                ? (string) $context->getLabel()
                : (string) $context;
            $counts[] = (int) $row->total;
        }

        return ['labels' => $labels, 'counts' => $counts];
    }

    /**
     * @return array{labels: list<string>, counts: list<int>}
     */
    public function getMarAdherenceSummary(PharmacyReportCriteria $criteria): array
    {
        $rows = MedicationAdministration::query()
            ->select('status', DB::raw('count(*) as total'))
            ->whereBetween('started_at', [$criteria->startDate->copy()->startOfDay(), $criteria->endDate->copy()->endOfDay()])
            ->when($criteria->branchId, function (Builder $q) use ($criteria): void {
                $q->whereHas('requestItem.serviceRequest', fn (Builder $sr) => $sr->where('branch_id', $criteria->branchId));
            })
            ->groupBy('status')
            ->get();

        $labels = [];
        $counts = [];

        foreach ($rows as $row) {
            $status = $row->status;
            $labels[] = $status instanceof MedicationAdministrationStatus
                ? (string) $status->getLabel()
                : (string) $status;
            $counts[] = (int) $row->total;
        }

        return ['labels' => $labels, 'counts' => $counts];
    }

    /**
     * @return array{labels: list<string>, counts: list<int>}
     */
    public function getStockMovementByReason(PharmacyReportCriteria $criteria): array
    {
        $rows = StockMovement::query()
            ->select('reason', DB::raw('count(*) as total'))
            ->whereBetween('created_at', [$criteria->startDate->copy()->startOfDay(), $criteria->endDate->copy()->endOfDay()])
            ->when($criteria->branchId, fn (Builder $q) => $q->where('branch_id', $criteria->branchId))
            ->groupBy('reason')
            ->get();

        return [
            'labels' => $rows->pluck('reason')->map(fn ($reason) => ucfirst((string) $reason))->all(),
            'counts' => $rows->pluck('total')->map(fn ($count) => (int) $count)->all(),
        ];
    }

    /**
     * @return list<array{
     *     id: string,
     *     medication: string,
     *     branch: string,
     *     quantity_on_hand: int,
     *     reorder_point: int
     * }>
     */
    public function getLowStockItems(?string $branchId = null, int $limit = 50): array
    {
        return StockItem::query()
            ->with(['medication.service', 'branch'])
            ->whereColumn('quantity_on_hand', '<=', 'reorder_point')
            ->when($branchId, fn (Builder $q) => $q->where('branch_id', $branchId))
            ->orderBy('quantity_on_hand')
            ->limit($limit)
            ->get()
            ->map(fn (StockItem $item): array => [
                'id' => (string) $item->id,
                'medication' => $item->medication?->displayName() ?? __('Unknown'),
                'branch' => $item->branch?->name ?? '—',
                'quantity_on_hand' => (int) $item->quantity_on_hand,
                'reorder_point' => (int) $item->reorder_point,
            ])
            ->all();
    }

    /**
     * @return list<array{
     *     id: string,
     *     dispensed_at: string,
     *     patient: string,
     *     medication: string,
     *     quantity: int,
     *     pharmacist: string,
     *     fulfillment_type: string
     * }>
     */
    public function getRecentDispenses(PharmacyReportCriteria $criteria, int $limit = 25): array
    {
        return Dispense::query()
            ->with([
                'medication.service',
                'dispensedBy',
                'requestItem.serviceRequest.patient',
            ])
            ->whereBetween('dispensed_at', [$criteria->startDate->copy()->startOfDay(), $criteria->endDate->copy()->endOfDay()])
            ->when($criteria->branchId, fn (Builder $q) => $this->scopeDispensesByBranch($q, $criteria->branchId))
            ->orderByDesc('dispensed_at')
            ->limit($limit)
            ->get()
            ->map(function (Dispense $dispense): array {
                $patient = $dispense->requestItem?->serviceRequest?->patient;

                return [
                    'id' => (string) $dispense->id,
                    'dispensed_at' => $dispense->dispensed_at?->toIso8601String() ?? '',
                    'patient' => $patient?->full_name ?? '—',
                    'medication' => $dispense->medication?->displayName() ?? __('Unknown'),
                    'quantity' => (int) $dispense->quantity,
                    'pharmacist' => $dispense->dispensedBy?->name ?? '—',
                    'fulfillment_type' => $dispense->fulfillment_type instanceof DispenseFulfillmentType
                        ? (string) $dispense->fulfillment_type->getLabel()
                        : (string) $dispense->fulfillment_type,
                ];
            })
            ->all();
    }

    /**
     * @return list<array{
     *     id: string,
     *     sold_at: string,
     *     customer: string,
     *     line_kind: string,
     *     description: string,
     *     amount: string,
     *     invoice_id: string
     * }>
     */
    public function getRecentPosSales(PharmacyReportCriteria $criteria, int $limit = 25): array
    {
        $posCriteria = new PharmacyReportCriteria(
            startDate: $criteria->startDate,
            endDate: $criteria->endDate,
            branchId: $criteria->branchId,
            lineKind: $criteria->lineKind === 'clinical' ? 'medication' : $criteria->lineKind,
            channel: 'pos',
        );

        return $this->revenueLinesQuery($posCriteria)
            ->with(['invoice.patient'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(function (InvoiceLine $line): array {
                $invoice = $line->invoice;
                $customer = $invoice?->patient?->full_name
                    ?? $invoice?->guest_name
                    ?? '—';

                $isService = $line->billable_type === null
                    && ($line->metadata['item_type'] ?? null) === 'service';

                return [
                    'id' => (string) $line->id,
                    'sold_at' => $line->created_at?->toIso8601String() ?? '',
                    'customer' => $customer,
                    'line_kind' => $isService ? 'service' : 'medication',
                    'description' => (string) ($line->description ?? '—'),
                    'amount' => $this->normalizeMoney((string) $line->line_total),
                    'invoice_id' => (string) $line->invoice_id,
                ];
            })
            ->all();
    }

    /**
     * @return list<array{
     *     id: string,
     *     dispensed_at: string,
     *     patient: string,
     *     medication: string,
     *     quantity: int,
     *     pharmacist: string
     * }>
     */
    public function getOutsidePurchaseDispenses(PharmacyReportCriteria $criteria, int $limit = 25): array
    {
        return Dispense::query()
            ->with([
                'medication.service',
                'dispensedBy',
                'requestItem.serviceRequest.patient',
            ])
            ->where('fulfillment_type', DispenseFulfillmentType::OUTSIDE_PURCHASE->value)
            ->whereBetween('dispensed_at', [$criteria->startDate->copy()->startOfDay(), $criteria->endDate->copy()->endOfDay()])
            ->when($criteria->branchId, fn (Builder $q) => $this->scopeDispensesByBranch($q, $criteria->branchId))
            ->orderByDesc('dispensed_at')
            ->limit($limit)
            ->get()
            ->map(function (Dispense $dispense): array {
                $patient = $dispense->requestItem?->serviceRequest?->patient;

                return [
                    'id' => (string) $dispense->id,
                    'dispensed_at' => $dispense->dispensed_at?->toIso8601String() ?? '',
                    'patient' => $patient?->full_name ?? '—',
                    'medication' => $dispense->medication?->displayName() ?? __('Unknown'),
                    'quantity' => (int) $dispense->quantity,
                    'pharmacist' => $dispense->dispensedBy?->name ?? '—',
                ];
            })
            ->all();
    }

    public function medicationRevenueLinesQuery(PharmacyReportCriteria $criteria): Builder
    {
        if ($criteria->lineKind === 'service') {
            return InvoiceLine::query()->whereRaw('1 = 0');
        }

        return $this->baseRevenueLinesQuery($criteria)->where(function (Builder $outer) use ($criteria): void {
            if ($criteria->channel === 'pos') {
                $outer->where(function (Builder $q): void {
                    $q->where('billable_type', $this->medicationMorphClass())
                        ->where('metadata->source', 'pharmacy_pos');
                });
            } elseif ($criteria->channel === 'clinical') {
                $outer->where('billable_type', $this->requestItemMorphClass());
            } else {
                $outer->where('billable_type', $this->medicationMorphClass())
                    ->orWhere('billable_type', $this->requestItemMorphClass());
            }
        });
    }

    public function serviceRevenueLinesQuery(PharmacyReportCriteria $criteria): Builder
    {
        if ($criteria->lineKind === 'medication' || $criteria->channel === 'clinical') {
            return InvoiceLine::query()->whereRaw('1 = 0');
        }

        return $this->baseRevenueLinesQuery($criteria)
            ->whereNull('billable_type')
            ->whereNull('billable_id')
            ->where('metadata->source', 'pharmacy_pos')
            ->where('metadata->item_type', 'service');
    }

    public function revenueLinesQuery(PharmacyReportCriteria $criteria): Builder
    {
        if ($criteria->lineKind === 'medication') {
            return $this->medicationRevenueLinesQuery($criteria);
        }

        if ($criteria->lineKind === 'service') {
            return $this->serviceRevenueLinesQuery($criteria);
        }

        return $this->baseRevenueLinesQuery($criteria)->where(function (Builder $outer) use ($criteria): void {
            $outer->where(function (Builder $medication) use ($criteria): void {
                if ($criteria->channel === 'pos') {
                    $medication->where('billable_type', $this->medicationMorphClass())
                        ->where('metadata->source', 'pharmacy_pos');
                } elseif ($criteria->channel === 'clinical') {
                    $medication->where('billable_type', $this->requestItemMorphClass());
                } else {
                    $medication->where('billable_type', $this->medicationMorphClass())
                        ->orWhere('billable_type', $this->requestItemMorphClass());
                }
            })->orWhere(function (Builder $service) use ($criteria): void {
                if ($criteria->channel === 'clinical') {
                    $service->whereRaw('1 = 0');
                } else {
                    $service->whereNull('billable_type')
                        ->whereNull('billable_id')
                        ->where('metadata->source', 'pharmacy_pos')
                        ->where('metadata->item_type', 'service');
                }
            });
        });
    }

    protected function baseRevenueLinesQuery(PharmacyReportCriteria $criteria): Builder
    {
        return InvoiceLine::query()
            ->where('line_status', '!=', InvoiceLineStatus::Void)
            ->whereBetween('created_at', [
                $criteria->startDate->copy()->startOfDay(),
                $criteria->endDate->copy()->endOfDay(),
            ])
            ->when($criteria->branchId, function (Builder $query) use ($criteria): void {
                $query->whereHas('invoice', fn (Builder $invoice) => $invoice->where('branch_id', $criteria->branchId));
            });
    }

    protected function pharmacyRequestItemsQuery(?string $branchId = null): Builder
    {
        return RequestItem::query()
            ->whereHas('prescriptionDetail')
            ->when($branchId, function (Builder $query) use ($branchId): void {
                $query->whereHas('serviceRequest', fn (Builder $sr) => $sr->where('branch_id', $branchId));
            });
    }

    protected function scopeDispensesByBranch(Builder $query, string $branchId): Builder
    {
        return $query->whereHas('requestItem.serviceRequest', fn (Builder $sr) => $sr->where('branch_id', $branchId));
    }

    protected function countDispensesInRange(PharmacyReportCriteria $criteria): int
    {
        return Dispense::query()
            ->whereBetween('dispensed_at', [$criteria->startDate->copy()->startOfDay(), $criteria->endDate->copy()->endOfDay()])
            ->when($criteria->branchId, fn (Builder $q) => $this->scopeDispensesByBranch($q, $criteria->branchId))
            ->count();
    }

    protected function countLowStock(?string $branchId = null): int
    {
        return StockItem::query()
            ->whereColumn('quantity_on_hand', '<=', 'reorder_point')
            ->when($branchId, fn (Builder $q) => $q->where('branch_id', $branchId))
            ->count();
    }

    protected function sumLineRevenue(Builder $query): string
    {
        return $this->normalizeMoney((string) (clone $query)->sum('line_total'));
    }

    protected function normalizeMoney(string $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    protected function medicationMorphClass(): string
    {
        return (new Medication)->getMorphClass();
    }

    protected function requestItemMorphClass(): string
    {
        return (new RequestItem)->getMorphClass();
    }
}
