<?php

namespace Modules\Pharmacy\Classes\Services;

use Illuminate\Support\Collection;
use Modules\Core\Models\Service;
use Modules\Pharmacy\Models\Drug;
use Modules\Pharmacy\Models\Medication;
use Modules\Pharmacy\Models\StockItem;

/**
 * Builds the option list for the prescribing "Medication" selects.
 *
 * Option values are the billing service id for catalog rows, `medication:<id>`
 * for a Medication without a service, and `drug:<id>` for a reference drug that
 * is not yet in the catalog. Labels are HTML (the selects use allowHtml()), so
 * every user-controlled string goes through e() here and nowhere else.
 */
class MedicationSearchOptionFormatter
{
    public function __construct(
        protected DrugSearchService $drugSearchService
    ) {}

    /**
     * @return array<string, string> option value => HTML label
     */
    public function searchOptions(string $search, ?string $branchId, int $limit = 10): array
    {
        $rows = collect($this->drugSearchService->search($search, $limit));
        $stock = $this->stockByMedication($rows->pluck('medication_id')->filter()->values(), $branchId);

        return $rows
            ->mapWithKeys(fn (array $row): array => $this->optionFor($row, $stock, $branchId))
            ->all();
    }

    /**
     * Label for an already-selected value, used when the form re-renders.
     */
    public function optionLabel(?string $value, ?string $branchId = null): ?string
    {
        if (blank($value)) {
            return null;
        }

        $row = $this->rowForValue((string) $value);

        if ($row === null) {
            return null;
        }

        $stock = $this->stockByMedication(collect([$row['medication_id']])->filter()->values(), $branchId);

        return array_values($this->optionFor($row, $stock, $branchId))[0] ?? null;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function rowForValue(string $value): ?array
    {
        if (str_starts_with($value, 'drug:')) {
            $drug = Drug::query()->find(str($value)->after('drug:')->toString());

            return $drug ? $this->drugRow($drug) : null;
        }

        if (str_starts_with($value, 'medication:')) {
            $medication = Medication::query()->with('service')->find(str($value)->after('medication:')->toString());

            return $medication ? $this->medicationRow($medication) : null;
        }

        $medication = Medication::query()->with('service')->where('service_id', $value)->first();

        if ($medication) {
            return $this->medicationRow($medication);
        }

        $service = Service::query()->find($value);

        return $service ? [
            'display_name' => $service->name,
            'is_formulary' => true,
            'drug_id' => null,
            'medication_id' => null,
            'service_id' => $service->id,
            'source_provider' => 'local',
        ] : null;
    }

    /**
     * @param  Collection<int, string>  $medicationIds
     * @return array<string, int> medication id => quantity on hand at the branch
     */
    protected function stockByMedication(Collection $medicationIds, ?string $branchId): array
    {
        if ($medicationIds->isEmpty() || blank($branchId)) {
            return [];
        }

        return StockItem::query()
            ->whereIn('medication_id', $medicationIds->all())
            ->where('branch_id', $branchId)
            ->pluck('quantity_on_hand', 'medication_id')
            ->map(fn ($quantity): int => (int) $quantity)
            ->all();
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, int>  $stock
     * @return array<string, string>
     */
    protected function optionFor(array $row, array $stock, ?string $branchId): array
    {
        $name = e((string) ($row['display_name'] ?? ''));

        if (filled($row['service_id'] ?? null) || filled($row['medication_id'] ?? null)) {
            $value = filled($row['service_id'] ?? null)
                ? (string) $row['service_id']
                : 'medication:'.$row['medication_id'];

            $prefix = ($row['is_formulary'] ?? true) ? '[Catalog] ' : '[Needs pricing] ';
            $badge = $this->stockBadge($row['medication_id'] ?? null, $stock, $branchId);

            return [$value => '<span class="font-medium">'.$prefix.$name.'</span> '.$badge];
        }

        if (filled($row['drug_id'] ?? null)) {
            $prefix = ($row['source_provider'] ?? null) === 'local' ? '[Reference] ' : '[External] ';

            return [
                'drug:'.$row['drug_id'] => '<span class="italic text-gray-500 dark:text-gray-400">'.$prefix.$name.'</span> '
                    .$this->badge('gray', 'Not stocked'),
            ];
        }

        return [];
    }

    /**
     * @param  array<string, int>  $stock
     */
    protected function stockBadge(?string $medicationId, array $stock, ?string $branchId): string
    {
        if (blank($branchId) || blank($medicationId)) {
            return $this->badge('gray', 'Stock unknown');
        }

        $quantity = $stock[$medicationId] ?? 0;

        return $quantity > 0
            ? $this->badge('success', $quantity.' in stock')
            : $this->badge('danger', 'Out of stock');
    }

    protected function badge(string $color, string $text): string
    {
        return '<span class="fi-badge fi-color fi-color-'.$color.' fi-size-sm">'.e($text).'</span>';
    }

    /**
     * @return array<string, mixed>
     */
    protected function drugRow(Drug $drug): array
    {
        return [
            'display_name' => $drug->display_name,
            'is_formulary' => false,
            'drug_id' => $drug->id,
            'medication_id' => null,
            'service_id' => null,
            'source_provider' => $drug->source_provider,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function medicationRow(Medication $medication): array
    {
        return [
            'display_name' => $medication->service?->name ?? $medication->generic_name,
            'is_formulary' => $medication->is_formulary,
            'drug_id' => null,
            'medication_id' => $medication->id,
            'service_id' => $medication->service_id,
            'source_provider' => 'local',
        ];
    }
}
