<?php

namespace Modules\Pharmacy\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Modules\Pharmacy\Classes\Services\MedicationMergeService;
use Modules\Pharmacy\Models\Medication;
use Throwable;

class MergeDuplicateMedicationsCommand extends Command
{
    protected $signature = 'pharmacy:merge-duplicate-medications
        {--force : Apply the merge (default is a dry-run report)}
        {--delete-services : Hard-delete duplicate billing services instead of deactivating them}
        {--include-name-matches : Also merge rows that only match by name/strength/form (review the dry-run first)}';

    protected $description = 'Merge duplicate Medications that share a drug link, RxNorm or NDC code into one row';

    public function handle(MedicationMergeService $mergeService): int
    {
        $force = (bool) $this->option('force');
        $includeNameMatches = (bool) $this->option('include-name-matches');

        $groups = $mergeService->findDuplicateGroups($includeNameMatches);

        if ($groups->isEmpty()) {
            $this->info('No duplicate medications found.');

            return self::SUCCESS;
        }

        if (! $force) {
            $this->warn('[DRY-RUN] No changes applied. Re-run with --force to merge.');
        } else {
            $this->warn('Run this outside pharmacy hours: open POS carts and stock checks key on medication ids.');
        }

        $failures = 0;

        foreach ($groups as $index => $group) {
            $this->newLine();
            $this->line(sprintf(
                '<options=bold>Group %d</> %s',
                $index + 1,
                $group['name_only'] ? '<fg=yellow>(possible match by name only, not merged)</>' : '',
            ));
            $this->line('Keys: '.implode(', ', $group['keys']));
            $this->table(
                ['Role', 'ID', 'Name', 'Drug link', 'Stock', 'Price', 'Formulary'],
                $group['medications']->map(fn (Medication $medication) => [
                    $medication->id === $group['canonical']->id ? 'keep' : 'merge',
                    $medication->id,
                    $medication->displayName(),
                    $medication->drug_id ? 'yes' : 'no',
                    (int) ($medication->stock_items_sum_quantity_on_hand ?? 0),
                    $medication->service?->price ?? '-',
                    $medication->is_formulary ? 'yes' : 'no',
                ])->all(),
            );

            if (! $force || $group['name_only']) {
                continue;
            }

            try {
                $report = $mergeService->merge(
                    $group['canonical'],
                    $group['duplicates'],
                    (bool) $this->option('delete-services'),
                );
                $this->printReport($report);
            } catch (Throwable $exception) {
                $failures++;
                $this->error("Group failed and was rolled back: {$exception->getMessage()}");
            }
        }

        $this->newLine();

        if ($failures > 0) {
            $this->error("{$failures} group(s) failed; earlier groups were committed.");

            return self::FAILURE;
        }

        $this->info($force ? 'Merge complete.' : 'Dry-run complete.');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, int|list<string>>  $report
     */
    protected function printReport(array $report): void
    {
        $counts = collect($report)
            ->except('manual_review')
            ->filter(fn ($count) => $count > 0)
            ->map(fn ($count, $key) => str_replace('_', ' ', $key).": {$count}");

        $this->info('Merged: '.($counts->isEmpty() ? 'nothing to move' : $counts->implode(', ')));

        foreach (Collection::wrap($report['manual_review'] ?? []) as $note) {
            $this->warn("Review: {$note}");
        }
    }
}
