<?php

namespace Modules\Pharmacy\Classes\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Pharmacy\Models\Medication;
use Modules\Pharmacy\Models\StockItem;
use Modules\Pharmacy\Models\StockMovement;

/**
 * Finds and merges duplicate Medications left behind by the old
 * prescribe-creates-a-medication behaviour.
 *
 * Only identity keys (drug_id, RxNorm, NDC) group rows for merging. Name
 * similarity is reported but never merged on its own: brand vs generic, or two
 * pack sizes sharing a name, are distinct stock lines rather than duplicates.
 */
class MedicationMergeService
{
    /**
     * @return Collection<int, array{
     *     medications: Collection<int, Medication>,
     *     canonical: Medication,
     *     duplicates: Collection<int, Medication>,
     *     keys: list<string>,
     *     name_only: bool
     * }>
     */
    public function findDuplicateGroups(bool $includeNameMatches = false): Collection
    {
        $medications = Medication::query()
            ->with('service')
            ->withSum('stockItems', 'quantity_on_hand')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $identityGroups = $this->partition($medications, fn (Medication $m): array => $this->identityKeys($m));
        $nameGroups = $this->partition($medications, fn (Medication $m): array => [
            ...$this->identityKeys($m),
            ...$this->nameKeys($m),
        ]);

        $identityMembership = $identityGroups
            ->flatMap(fn (Collection $group, int $index) => $group->mapWithKeys(fn (Medication $m) => [$m->id => $index]));

        $groups = collect();

        foreach ($nameGroups as $group) {
            if ($group->count() < 2) {
                continue;
            }

            $identityComponents = $group->map(fn (Medication $m) => $identityMembership[$m->id])->unique();
            $nameOnly = $identityComponents->count() > 1;

            if ($nameOnly && ! $includeNameMatches) {
                // Report each identity component that is itself a duplicate set,
                // and the whole thing as a possible match for manual review.
                foreach ($identityComponents as $component) {
                    $members = $identityGroups[$component];
                    if ($members->count() >= 2) {
                        $groups->push($this->describeGroup($members, false));
                    }
                }
                $groups->push($this->describeGroup($group, true));

                continue;
            }

            $groups->push($this->describeGroup($group, false));
        }

        return $groups->values();
    }

    /**
     * Fold every duplicate into the canonical row. Executed inside one
     * transaction per group so a partial merge never persists.
     *
     * @param  Collection<int, Medication>  $duplicates
     * @return array<string, int|list<string>>
     */
    public function merge(Medication $canonical, Collection $duplicates, bool $deleteServices = false): array
    {
        return DB::transaction(function () use ($canonical, $duplicates, $deleteServices): array {
            $canonical->loadMissing(['service', 'stockUnit']);
            $duplicates->each(fn (Medication $duplicate) => $duplicate->loadMissing('service'));

            $report = [
                'stock_items_moved' => 0,
                'stock_quantity_merged' => 0,
                'stock_movements_moved' => 0,
                'dispenses_moved' => 0,
                'request_items_moved' => 0,
                'invoice_lines_moved' => 0,
                'appointments_moved' => 0,
                'inventory_items_moved' => 0,
                'immunization_records_moved' => 0,
                'services_deactivated' => 0,
                'services_deleted' => 0,
                'manual_review' => [],
            ];

            foreach ($duplicates as $duplicate) {
                $this->mergeStock($canonical, $duplicate, $report);

                $report['stock_movements_moved'] += StockMovement::query()
                    ->where('medication_id', $duplicate->id)
                    ->update(['medication_id' => $canonical->id]);

                $report['dispenses_moved'] += DB::table('dispenses')
                    ->where('medication_id', $duplicate->id)
                    ->update(['medication_id' => $canonical->id]);

                $this->mergeCrossModuleMedicationLinks($canonical, $duplicate, $report);
                $this->mergeServices($canonical, $duplicate, $deleteServices, $report);
                $this->mergeIdentity($canonical, $duplicate);

                $duplicate->delete();
            }

            $canonical->save();

            return $report;
        });
    }

    /**
     * Most stock wins, then a priced service, then an explicit drug link, then
     * the oldest row.
     *
     * @param  Collection<int, Medication>  $group
     */
    public function pickCanonical(Collection $group): Medication
    {
        return $group
            ->sortBy([
                fn (Medication $a, Medication $b) => ($b->stock_items_sum_quantity_on_hand ?? 0) <=> ($a->stock_items_sum_quantity_on_hand ?? 0),
                fn (Medication $a, Medication $b) => (int) (($b->service?->price ?? 0) > 0) <=> (int) (($a->service?->price ?? 0) > 0),
                fn (Medication $a, Medication $b) => (int) ($b->drug_id !== null) <=> (int) ($a->drug_id !== null),
                fn (Medication $a, Medication $b) => $a->created_at <=> $b->created_at,
                fn (Medication $a, Medication $b) => strcmp($a->id, $b->id),
            ])
            ->first();
    }

    /**
     * @param  Collection<int, Medication>  $members
     * @return array{medications: Collection<int, Medication>, canonical: Medication, duplicates: Collection<int, Medication>, keys: list<string>, name_only: bool}
     */
    protected function describeGroup(Collection $members, bool $nameOnly): array
    {
        $canonical = $this->pickCanonical($members);

        return [
            'medications' => $members->values(),
            'canonical' => $canonical,
            'duplicates' => $members->reject(fn (Medication $m) => $m->id === $canonical->id)->values(),
            'keys' => $members->flatMap(fn (Medication $m) => [...$this->identityKeys($m), ...$this->nameKeys($m)])->unique()->values()->all(),
            'name_only' => $nameOnly,
        ];
    }

    /**
     * Union-find over shared keys.
     *
     * @param  Collection<int, Medication>  $medications
     * @param  callable(Medication): list<string>  $keysFor
     * @return Collection<int, Collection<int, Medication>>
     */
    protected function partition(Collection $medications, callable $keysFor): Collection
    {
        $parent = [];
        $find = function (string $id) use (&$parent, &$find): string {
            if ($parent[$id] !== $id) {
                $parent[$id] = $find($parent[$id]);
            }

            return $parent[$id];
        };
        $union = function (string $a, string $b) use (&$parent, $find): void {
            $parent[$find($a)] = $find($b);
        };

        $ownerByKey = [];

        foreach ($medications as $medication) {
            $parent[$medication->id] = $medication->id;

            foreach ($keysFor($medication) as $key) {
                if (isset($ownerByKey[$key])) {
                    $union($medication->id, $ownerByKey[$key]);
                } else {
                    $ownerByKey[$key] = $medication->id;
                }
            }
        }

        return $medications
            ->groupBy(fn (Medication $m) => $find($m->id))
            ->values();
    }

    /**
     * @return list<string>
     */
    protected function identityKeys(Medication $medication): array
    {
        return array_values(array_filter([
            $medication->drug_id ? 'drug:'.$medication->drug_id : null,
            filled($medication->rxnorm_code) ? 'rxnorm:'.trim($medication->rxnorm_code) : null,
            filled($medication->ndc_code) ? 'ndc:'.trim($medication->ndc_code) : null,
        ]));
    }

    /**
     * @return list<string>
     */
    protected function nameKeys(Medication $medication): array
    {
        $parts = [
            $medication->generic_name,
            $medication->brand_name,
            $medication->strength,
            enum_string($medication->dosage_form) ?? '',
        ];

        $normalized = collect($parts)
            ->map(fn ($part) => Str::of((string) $part)->lower()->squish()->toString())
            ->implode('|');

        return trim($normalized, '|') === '' ? [] : ['name:'.$normalized];
    }

    /**
     * @param  array<string, mixed>  $report
     */
    protected function mergeStock(Medication $canonical, Medication $duplicate, array &$report): void
    {
        $duplicateItems = StockItem::query()->where('medication_id', $duplicate->id)->get();

        foreach ($duplicateItems as $item) {
            $existing = StockItem::query()
                ->where('medication_id', $canonical->id)
                ->where('branch_id', $item->branch_id)
                ->first();

            if (! $existing) {
                $item->update(['medication_id' => $canonical->id]);
                $report['stock_items_moved']++;

                continue;
            }

            $existing->update([
                'quantity_on_hand' => $existing->quantity_on_hand + $item->quantity_on_hand,
                'reorder_point' => max($existing->reorder_point, $item->reorder_point),
            ]);

            if ($item->quantity_on_hand > 0) {
                StockMovement::query()->create([
                    'branch_id' => $item->branch_id,
                    'medication_id' => $canonical->id,
                    'delta' => $item->quantity_on_hand,
                    'quantity_after' => $existing->quantity_on_hand,
                    'unit_label_snapshot' => $canonical->stockUnit?->label,
                    'reason' => 'merge_from:'.$duplicate->id,
                ]);
            }

            $report['stock_quantity_merged'] += $item->quantity_on_hand;
            $item->delete();
        }
    }

    /**
     * @param  array<string, mixed>  $report
     */
    protected function mergeCrossModuleMedicationLinks(Medication $canonical, Medication $duplicate, array &$report): void
    {
        if (Schema::hasTable('inventory_items')) {
            $canonicalHasItem = DB::table('inventory_items')->where('medication_id', $canonical->id)->exists();

            if ($canonicalHasItem) {
                $orphaned = DB::table('inventory_items')->where('medication_id', $duplicate->id)->pluck('id');
                if ($orphaned->isNotEmpty()) {
                    DB::table('inventory_items')->whereIn('id', $orphaned)->update(['medication_id' => null]);
                    $report['manual_review'][] = 'inventory_items '.$orphaned->implode(', ')." unlinked (canonical {$canonical->id} already has an inventory item)";
                }
            } else {
                $report['inventory_items_moved'] += DB::table('inventory_items')
                    ->where('medication_id', $duplicate->id)
                    ->update(['medication_id' => $canonical->id]);
            }
        }

        if (Schema::hasTable('immunization_records')) {
            $report['immunization_records_moved'] += DB::table('immunization_records')
                ->where('medication_id', $duplicate->id)
                ->update(['medication_id' => $canonical->id]);
        }
    }

    /**
     * request_items.service_id cascades on service delete, so every consumer
     * of the duplicate service is repointed before the service is touched.
     *
     * @param  array<string, mixed>  $report
     */
    protected function mergeServices(Medication $canonical, Medication $duplicate, bool $deleteServices, array &$report): void
    {
        $duplicateService = $duplicate->service;

        if (! $duplicateService) {
            return;
        }

        if (! $canonical->service_id) {
            $duplicate->update(['service_id' => null]);
            $canonical->update(['service_id' => $duplicateService->id]);
            $canonical->setRelation('service', $duplicateService);

            return;
        }

        $canonicalService = $canonical->service;

        $report['request_items_moved'] += DB::table('request_items')
            ->where('service_id', $duplicateService->id)
            ->update(['service_id' => $canonicalService->id]);

        if (Schema::hasTable('invoice_lines')) {
            $report['invoice_lines_moved'] += DB::table('invoice_lines')
                ->where('service_id', $duplicateService->id)
                ->update(['service_id' => $canonicalService->id]);
        }

        if (Schema::hasTable('appointments') && Schema::hasColumn('appointments', 'service_id')) {
            $report['appointments_moved'] += DB::table('appointments')
                ->where('service_id', $duplicateService->id)
                ->update(['service_id' => $canonicalService->id]);
        }

        if ((float) $canonicalService->price <= 0 && (float) $duplicateService->price > 0) {
            $canonicalService->update(['price' => $duplicateService->price]);
            $report['manual_review'][] = "price {$duplicateService->price} copied from merged service {$duplicateService->id}";
        }

        $duplicate->update(['service_id' => null]);

        if ($deleteServices) {
            $duplicateService->delete();
            $report['services_deleted']++;

            return;
        }

        $duplicateService->update([
            'is_active' => false,
            'metadata' => array_merge($duplicateService->metadata ?? [], [
                'merged_into_service_id' => $canonicalService->id,
                'merged_at' => now()->toIso8601String(),
            ]),
        ]);
        $report['services_deactivated']++;
    }

    protected function mergeIdentity(Medication $canonical, Medication $duplicate): void
    {
        if ($canonical->drug_id === null && $duplicate->drug_id !== null) {
            $drugId = $duplicate->drug_id;
            $duplicate->update(['drug_id' => null]);
            $canonical->drug_id = $drugId;
        }

        foreach (['rxnorm_code', 'ndc_code', 'brand_name', 'strength', 'controlled_schedule'] as $attribute) {
            if (blank($canonical->{$attribute}) && filled($duplicate->{$attribute})) {
                $canonical->{$attribute} = $duplicate->{$attribute};
            }
        }

        $canonical->is_formulary = $canonical->is_formulary || $duplicate->is_formulary;
    }
}
