<?php

namespace Modules\Pharmacy\Classes\Services;

use Illuminate\Support\Facades\DB;
use Modules\Billing\Enums\InvoiceLineStatus;
use Modules\Billing\Enums\InvoiceType;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Models\InvoiceLine;
use Modules\Billing\Services\InvoiceAllocationBuilder;
use Modules\Billing\Services\InvoiceIssuanceService;
use Modules\Billing\Services\InvoiceTotalsService;
use Modules\Billing\Services\ManualInvoiceService;
use Modules\Billing\Services\PatientPendingChargesService;
use Modules\Billing\Services\PaymentRecordingService;
use Modules\Core\Contracts\InsurancePricingResolver;
use Modules\Core\Contracts\StockProviderContract;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Service;
use Modules\Core\Support\ModuleAvailability;
use Modules\Pharmacy\Classes\Support\PharmacyPosTotals;
use Modules\Pharmacy\Exceptions\InsufficientStockException;
use Modules\Pharmacy\Models\Medication;

class PharmacyPosCheckoutService
{
    public function __construct(
        protected StockProviderContract $stockProvider,
        protected InsurancePricingResolver $insurancePricing,
        protected ManualInvoiceService $manualInvoiceService,
        protected InvoiceTotalsService $totalsService,
        protected InvoiceIssuanceService $issuanceService,
        protected InvoiceAllocationBuilder $allocationBuilder,
        protected PaymentRecordingService $paymentRecordingService,
        protected PatientPendingChargesService $pendingCharges,
    ) {}

    /**
     * Cart rows of type `charge` point at invoice lines the patient already owes for
     * ordered services or prescriptions. They are settled on their own invoices and never
     * produce new lines or stock movements; everything else is a walk-in sale.
     *
     * @param  list<array<string, mixed>>  $cart
     * @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>} [charge rows, walk-in rows]
     */
    public static function splitChargeRows(array $cart): array
    {
        $charges = array_values(array_filter($cart, fn (array $row): bool => ($row['type'] ?? 'medication') === 'charge'));
        $walkIn = array_values(array_filter($cart, fn (array $row): bool => ($row['type'] ?? 'medication') !== 'charge'));

        return [$charges, $walkIn];
    }

    public function checkout(array $data): array
    {
        $this->ensureBillingAvailable();

        $branchId = $data['branch_id'];
        if ($branchId === null || $branchId === '') {
            throw new \InvalidArgumentException('Branch is required for POS checkout.');
        }

        $patientId = $data['patient_id'] ?? null;
        $currency = $data['currency'] ?? config('core.default_currency');
        [$chargeRows, $cart] = self::splitChargeRows($data['cart']);
        $method = $data['payment_method'] ?? PaymentMethod::Cash;
        $posDiscount = PharmacyPosTotals::normalizeMoney($data['pos_discount_amount'] ?? 0);
        $amountTendered = isset($data['amount_tendered']) && $data['amount_tendered'] !== null && $data['amount_tendered'] !== ''
            ? PharmacyPosTotals::normalizeMoney($data['amount_tendered'])
            : null;

        Branch::query()->findOrFail($branchId);

        if ($chargeRows === [] && $cart === []) {
            throw new \InvalidArgumentException('The cart is empty.');
        }

        $chargeLineIds = array_values(array_filter(array_map(fn (array $row): string => (string) ($row['invoice_line_id'] ?? ''), $chargeRows)));

        if ($chargeLineIds !== [] && ! $patientId) {
            throw new \InvalidArgumentException('Select the patient before paying their pending charges.');
        }

        $chargeTotal = $chargeLineIds !== []
            ? $this->pendingCharges->remainingFor($chargeLineIds, (string) $patientId)
            : '0.00';

        if ($chargeTotal === null) {
            throw new \InvalidArgumentException('One of the ordered items in the cart is no longer pending payment. Remove it and try again.');
        }

        $medRows = array_filter($cart, fn ($r) => ($r['type'] ?? 'medication') === 'medication');
        $svcRows = array_filter($cart, fn ($r) => ($r['type'] ?? 'medication') === 'service');

        $medications = collect();
        if (! empty($medRows)) {
            $medIds = array_column($medRows, 'id');
            $medications = Medication::query()
                ->with(['service', 'billingUnit'])
                ->whereIn('id', $medIds)
                ->where('is_active', true)
                ->get()
                ->keyBy('id');

            foreach ($medRows as $row) {
                if (! isset($medications[$row['id']])) {
                    throw new \InvalidArgumentException("Medication {$row['id']} not found or inactive.");
                }
                $medication = $medications[$row['id']];
                if (! $medication->billingService()) {
                    throw new \InvalidArgumentException(
                        "{$medication->displayName()} is not configured for billing. Set a price in Medications."
                    );
                }
            }

            foreach ($medRows as $row) {
                if (! $this->stockProvider->hasStock($branchId, $row['id'], (int) $row['quantity'])) {
                    throw new InsufficientStockException('Insufficient stock to complete POS sale.');
                }
            }

            foreach ($medRows as $row) {
                $medication = $medications[$row['id']];
                if ($medication->controlled_schedule !== null) {
                    throw new \InvalidArgumentException(
                        "{$medication->service?->name} is a controlled substance and cannot be sold via POS. Use clinical dispensing."
                    );
                }
            }
        }

        $services = collect();
        if (! empty($svcRows)) {
            $svcIds = array_column($svcRows, 'id');
            $services = Service::query()
                ->withoutGlobalScope('branch')
                ->where('branch_id', $branchId)
                ->whereIn('id', $svcIds)
                ->fixed()
                ->where('is_active', true)
                ->where('is_billable', true)
                ->get()
                ->keyBy('id');

            foreach ($svcRows as $row) {
                if (! isset($services[$row['id']])) {
                    throw new \InvalidArgumentException("Service {$row['id']} not found or inactive.");
                }
            }
        }

        $lineSubtotals = [];
        foreach ($cart as $row) {
            $isMed = ($row['type'] ?? 'medication') === 'medication';
            if ($isMed) {
                $medication = $medications[$row['id']];
                $unitPrice = (string) ($medication->service->price ?? '0');
            } else {
                $svc = $services[$row['id']];
                $unitPrice = (string) ($svc->price ?? '0');
            }
            $lineSubtotals[] = bcmul($unitPrice, (string) $row['quantity'], 2);
        }

        $discounts = $this->distributeDiscountAcrossLines($lineSubtotals, $posDiscount);

        return DB::transaction(function () use ($branchId, $patientId, $currency, $cart, $method, $medications, $services, $medRows, $data, $discounts, $posDiscount, $amountTendered, $chargeLineIds, $chargeTotal) {
            $invoice = null;
            $invoiceTotal = '0.00';

            if ($cart !== []) {
                $invoice = $this->manualInvoiceService->createStandaloneDraft(
                    branchId: $branchId,
                    patientId: $patientId,
                    currency: $currency,
                    invoiceType: InvoiceType::Standalone,
                );

                if (! $patientId) {
                    $invoice->guest_name = $data['guest_name'] ?? null;
                    $invoice->guest_phone = $data['guest_phone'] ?? null;
                    $invoice->guest_email = $data['guest_email'] ?? null;
                    $invoice->save();
                }

                foreach ($cart as $index => $row) {
                    $isMed = ($row['type'] ?? 'medication') === 'medication';
                    if ($isMed) {
                        $sourceItem = $medications[$row['id']];
                        $service = $sourceItem->service;
                        $unitPrice = (string) ($service->price ?? '0');
                    } else {
                        $sourceItem = $services[$row['id']];
                        $service = $sourceItem;
                        $unitPrice = (string) ($sourceItem->price ?? '0');
                    }

                    $quantity = (int) $row['quantity'];
                    $lineDiscount = $discounts[$index] ?? '0.00';
                    $gross = bcmul($unitPrice, (string) $quantity, 2);
                    $netBeforeTax = bcsub($gross, $lineDiscount, 2);
                    if (bccomp($netBeforeTax, '0', 2) < 0) {
                        throw new \InvalidArgumentException('Invalid discount for line items.');
                    }

                    $lineData = [
                        'invoice_id' => $invoice->id,
                        'service_id' => $service->id,
                        'description' => $service->name ?? $sourceItem->name,
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'discount_amount' => $lineDiscount,
                        'tax_amount' => '0',
                        'amount_paid' => '0',
                        'line_status' => InvoiceLineStatus::Unpaid,
                        'patient_responsibility_amount' => $netBeforeTax,
                        'metadata' => ['source' => 'pharmacy_pos'],
                    ];

                    if ($isMed) {
                        $lineData['billable_type'] = $sourceItem->getMorphClass();
                        $lineData['billable_id'] = $sourceItem->id;
                        $lineData['unit_id'] = $sourceItem->billing_unit_id;
                        $lineData['unit_label_snapshot'] = $sourceItem->billingUnit?->label;
                    } else {
                        $lineData['billable_type'] = null;
                        $lineData['billable_id'] = null;
                        $lineData['unit_id'] = null;
                        $lineData['unit_label_snapshot'] = null;
                        $lineData['metadata']['item_type'] = 'service';
                    }

                    if ($patientId) {
                        $pricing = $this->insurancePricing->resolveForItem(
                            patientId: $patientId,
                            itemType: 'service',
                            externalCode: (string) $service->id,
                            fallbackAmount: $gross,
                        );
                        $lineData['insurance_expected_amount'] = $pricing['insurer_amount'];
                        $lineData['patient_responsibility_amount'] = $pricing['patient_amount'];
                        $lineData['metadata'] = array_merge($lineData['metadata'], [
                            'insurance_policy_id' => $pricing['policy_id'],
                            'insurance_payer_id' => $pricing['payer_id'],
                            'insurance_source_version' => $pricing['source_version'],
                        ]);
                    }

                    InvoiceLine::query()->create($lineData);
                }

                $invoice = $this->totalsService->recalculate($invoice->fresh(['lines']));

                $invoice = $this->issuanceService->issue($invoice->fresh(['lines']));

                $invoiceTotal = (string) $invoice->total;
            }

            $grandTotal = bcadd($chargeTotal, $invoiceTotal, 2);

            if ($amountTendered !== null && bccomp($amountTendered, $grandTotal, 2) < 0) {
                throw new \InvalidArgumentException('Amount tendered is less than the amount due.');
            }

            $paymentMetadata = [
                'source' => 'pharmacy_pos',
                'pos_discount_amount' => $posDiscount,
            ];
            if ($amountTendered !== null) {
                $paymentMetadata['amount_tendered'] = $amountTendered;
                $paymentMetadata['change_due'] = bcsub($amountTendered, $grandTotal, 2);
            }

            // Ordered items are paid on the invoices they already sit on, so the order's
            // payment status and any financial hold clear the same way as at the billing desk.
            $settledPayments = $chargeLineIds !== []
                ? $this->pendingCharges->settle(
                    lineIds: $chargeLineIds,
                    patientId: (string) $patientId,
                    branchId: $branchId,
                    method: $method,
                    currency: $currency,
                    recordedBy: auth()->id(),
                    metadata: $paymentMetadata + ['settled_pending_charges' => true],
                )
                : collect();

            $payment = null;

            if ($invoice !== null) {
                $allocations = $this->allocationBuilder->allocateAmountAcrossUnpaidLines($invoice, $invoiceTotal);

                $payment = $this->paymentRecordingService->record(
                    allocations: $allocations,
                    method: $method,
                    gateway: $method->value,
                    currency: $currency,
                    patientId: $patientId,
                    branchId: $branchId,
                    recordedBy: auth()->id(),
                    metadata: $paymentMetadata,
                );

                foreach ($medRows as $row) {
                    $this->stockProvider->decrement(
                        branchId: $branchId,
                        itemId: $row['id'],
                        quantity: (int) $row['quantity'],
                        reason: 'pos_sale',
                    );
                }
            }

            $payments = $settledPayments->values();
            if ($payment !== null) {
                $payments->prepend($payment);
            }

            $settledInvoices = $settledPayments
                ->flatMap(fn ($settled) => $settled->fresh(['allocations.invoiceLine.invoice'])->allocations->map(fn ($allocation) => $allocation->invoiceLine?->invoice))
                ->filter()
                ->unique('id')
                ->values();

            return [
                'invoice' => $invoice?->fresh(['lines']) ?? $settledInvoices->first(),
                'payment' => $payments->first()?->fresh(['allocations']),
                'invoices' => $invoice !== null ? $settledInvoices->prepend($invoice->fresh(['lines'])) : $settledInvoices,
                'payments' => $payments,
                'grand_total' => $grandTotal,
            ];
        });
    }

    public function checkoutChargeToAccount(array $data): array
    {
        $this->ensureBillingAvailable();

        $branchId = $data['branch_id'];
        if ($branchId === null || $branchId === '') {
            throw new \InvalidArgumentException('Branch is required for POS checkout.');
        }

        $patientId = $data['patient_id'] ?? null;

        $currency = $data['currency'] ?? config('core.default_currency');
        // Ordered items are already on the patient's account; only walk-in rows are posted.
        [, $cart] = self::splitChargeRows($data['cart']);
        $posDiscount = PharmacyPosTotals::normalizeMoney($data['pos_discount_amount'] ?? 0);

        Branch::query()->findOrFail($branchId);

        if ($cart === []) {
            throw new \InvalidArgumentException('The ordered items in the cart are already on the patient\'s account. Choose "Pay now" to collect payment, or settle them on the Billing Desk page.');
        }

        $medRows = array_filter($cart, fn ($r) => ($r['type'] ?? 'medication') === 'medication');
        $svcRows = array_filter($cart, fn ($r) => ($r['type'] ?? 'medication') === 'service');

        $medications = collect();
        if (! empty($medRows)) {
            $medIds = array_column($medRows, 'id');
            $medications = Medication::query()
                ->with(['service', 'billingUnit'])
                ->whereIn('id', $medIds)
                ->where('is_active', true)
                ->get()
                ->keyBy('id');

            foreach ($medRows as $row) {
                if (! isset($medications[$row['id']])) {
                    throw new \InvalidArgumentException("Medication {$row['id']} not found or inactive.");
                }
                $medication = $medications[$row['id']];
                if (! $medication->billingService()) {
                    throw new \InvalidArgumentException(
                        "{$medication->displayName()} is not configured for billing. Set a price in Medications."
                    );
                }
            }

            foreach ($medRows as $row) {
                $medication = $medications[$row['id']];
                if ($medication->controlled_schedule !== null) {
                    throw new \InvalidArgumentException(
                        "{$medication->service?->name} is a controlled substance and cannot be sold via POS. Use clinical dispensing."
                    );
                }
            }

            foreach ($medRows as $row) {
                if (! $this->stockProvider->hasStock($branchId, $row['id'], (int) $row['quantity'])) {
                    throw new InsufficientStockException('Insufficient stock to complete POS sale.');
                }
            }
        }

        $services = collect();
        if (! empty($svcRows)) {
            $svcIds = array_column($svcRows, 'id');
            $services = Service::query()
                ->withoutGlobalScope('branch')
                ->where('branch_id', $branchId)
                ->whereIn('id', $svcIds)
                ->fixed()
                ->where('is_active', true)
                ->where('is_billable', true)
                ->get()
                ->keyBy('id');

            foreach ($svcRows as $row) {
                if (! isset($services[$row['id']])) {
                    throw new \InvalidArgumentException("Service {$row['id']} not found or inactive.");
                }
            }
        }

        $lineSubtotals = [];
        foreach ($cart as $row) {
            $isMed = ($row['type'] ?? 'medication') === 'medication';
            if ($isMed) {
                $medication = $medications[$row['id']];
                $unitPrice = (string) ($medication->service->price ?? '0');
            } else {
                $svc = $services[$row['id']];
                $unitPrice = (string) ($svc->price ?? '0');
            }
            $lineSubtotals[] = bcmul($unitPrice, (string) $row['quantity'], 2);
        }

        $discounts = $this->distributeDiscountAcrossLines($lineSubtotals, $posDiscount);

        return DB::transaction(function () use ($branchId, $patientId, $currency, $cart, $medications, $services, $medRows, $data, $discounts, $posDiscount) {
            $invoice = $this->manualInvoiceService->createStandaloneDraft(
                branchId: $branchId,
                patientId: $patientId,
                currency: $currency,
                invoiceType: InvoiceType::Standalone,
            );

            if (! $patientId) {
                $invoice->guest_name = $data['guest_name'] ?? null;
                $invoice->guest_phone = $data['guest_phone'] ?? null;
                $invoice->guest_email = $data['guest_email'] ?? null;
                $invoice->save();
            }

            foreach ($cart as $index => $row) {
                $isMed = ($row['type'] ?? 'medication') === 'medication';
                if ($isMed) {
                    $sourceItem = $medications[$row['id']];
                    $service = $sourceItem->service;
                    $unitPrice = (string) ($service->price ?? '0');
                } else {
                    $sourceItem = $services[$row['id']];
                    $service = $sourceItem;
                    $unitPrice = (string) ($sourceItem->price ?? '0');
                }

                $quantity = (int) $row['quantity'];
                $lineDiscount = $discounts[$index] ?? '0.00';
                $gross = bcmul($unitPrice, (string) $quantity, 2);
                $netBeforeTax = bcsub($gross, $lineDiscount, 2);
                if (bccomp($netBeforeTax, '0', 2) < 0) {
                    throw new \InvalidArgumentException('Invalid discount for line items.');
                }

                $lineData = [
                    'invoice_id' => $invoice->id,
                    'service_id' => $service->id,
                    'description' => $service->name ?? $sourceItem->name,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount_amount' => $lineDiscount,
                    'tax_amount' => '0',
                    'amount_paid' => '0',
                    'line_status' => InvoiceLineStatus::Unpaid,
                    'patient_responsibility_amount' => $netBeforeTax,
                    'metadata' => ['source' => 'pharmacy_pos'],
                ];

                if ($isMed) {
                    $lineData['billable_type'] = $sourceItem->getMorphClass();
                    $lineData['billable_id'] = $sourceItem->id;
                    $lineData['unit_id'] = $sourceItem->billing_unit_id;
                    $lineData['unit_label_snapshot'] = $sourceItem->billingUnit?->label;
                } else {
                    $lineData['billable_type'] = null;
                    $lineData['billable_id'] = null;
                    $lineData['unit_id'] = null;
                    $lineData['unit_label_snapshot'] = null;
                    $lineData['metadata']['item_type'] = 'service';
                }

                if ($patientId) {
                    $pricing = $this->insurancePricing->resolveForItem(
                        patientId: $patientId,
                        itemType: 'service',
                        externalCode: (string) $service->id,
                        fallbackAmount: $gross,
                    );
                    $lineData['insurance_expected_amount'] = $pricing['insurer_amount'];
                    $lineData['patient_responsibility_amount'] = $pricing['patient_amount'];
                    $lineData['metadata'] = array_merge($lineData['metadata'], [
                        'insurance_policy_id' => $pricing['policy_id'],
                        'insurance_payer_id' => $pricing['payer_id'],
                        'insurance_source_version' => $pricing['source_version'],
                    ]);
                }

                InvoiceLine::query()->create($lineData);
            }

            $invoice = $this->totalsService->recalculate($invoice->fresh(['lines']));

            $invoiceMeta = array_merge((array) ($invoice->metadata ?? []), [
                'source' => 'pharmacy_pos',
                'charge_mode' => 'account',
                'pos_discount_amount' => $posDiscount,
            ]);
            $invoice->metadata = $invoiceMeta;
            $invoice->save();

            $invoice = $this->issuanceService->issue($invoice->fresh(['lines']));

            if ($medRows) {
                foreach ($medRows as $row) {
                    $this->stockProvider->decrement(
                        branchId: $branchId,
                        itemId: $row['id'],
                        quantity: (int) $row['quantity'],
                        reason: 'pos_sale',
                    );
                }
            }

            return [
                'invoice' => $invoice->fresh(['lines']),
                'payment' => null,
            ];
        });
    }

    /**
     * @param  array<int, string>  $lineSubtotals  Gross line amounts (unit × qty), scale 2
     * @return array<int, string> Discount per line, scale 2
     */
    private function distributeDiscountAcrossLines(array $lineSubtotals, string $discountTotal): array
    {
        $n = count($lineSubtotals);
        if ($n === 0) {
            return [];
        }

        if (bccomp($discountTotal, '0', 2) <= 0) {
            return array_fill(0, $n, '0.00');
        }

        $sum = '0.00';
        foreach ($lineSubtotals as $subtotal) {
            $sum = bcadd($sum, $subtotal, 2);
        }

        if (bccomp($discountTotal, $sum, 2) > 0) {
            throw new \InvalidArgumentException('Discount cannot exceed cart subtotal.');
        }

        $out = [];
        $assigned = '0.00';

        for ($i = 0; $i < $n - 1; $i++) {
            $proportion = bcdiv($lineSubtotals[$i], $sum, 8);
            $share = bcmul($discountTotal, $proportion, 4);
            $shareRounded = sprintf('%.2f', round((float) $share, 2));
            if (bccomp($shareRounded, $lineSubtotals[$i], 2) > 0) {
                $shareRounded = $lineSubtotals[$i];
            }
            $out[$i] = $shareRounded;
            $assigned = bcadd($assigned, $shareRounded, 2);
        }

        $last = bcsub($discountTotal, $assigned, 2);
        if (bccomp($last, $lineSubtotals[$n - 1], 2) > 0) {
            throw new \InvalidArgumentException('Unable to apply discount across cart lines.');
        }
        if (bccomp($last, '0', 2) < 0) {
            $last = '0.00';
        }
        $out[$n - 1] = $last;

        return $out;
    }

    protected function ensureBillingAvailable(): void
    {
        if (! ModuleAvailability::billingEnabled()) {
            throw new \RuntimeException('Billing module is required for pharmacy POS checkout.');
        }
    }
}
