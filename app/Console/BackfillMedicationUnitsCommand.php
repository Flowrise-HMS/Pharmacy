<?php

namespace Modules\Pharmacy\Console;

use Illuminate\Console\Command;
use Modules\Pharmacy\Classes\Support\UnitResolver;
use Modules\Pharmacy\Models\Medication;

class BackfillMedicationUnitsCommand extends Command
{
    protected $signature = 'pharmacy:backfill-medication-units
        {--dry-run : Preview changes without executing}';

    protected $description = 'Backfill medication unit columns from dosage_form defaults';

    public function handle(UnitResolver $resolver): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $medications = Medication::whereNull('stock_unit_id')
            ->orWhereNull('billing_unit_id')
            ->get();

        if ($medications->isEmpty()) {
            $this->info('All medications already have units assigned.');

            return self::SUCCESS;
        }

        $this->line("Found {$medications->count()} medication(s) missing unit assignments.");

        $bar = $this->output->createProgressBar($medications->count());
        $bar->start();

        $updated = 0;
        foreach ($medications as $medication) {
            if (! $medication->dosage_form) {
                $bar->advance();
                continue;
            }

            $defaults = $resolver->defaultsForDosageForm($medication->dosage_form);
            $stockUnitId = $defaults['stock_unit']?->id;
            $billingUnitId = $defaults['billing_unit']?->id;
            $doseUnitId = $defaults['dose_unit']?->id;

            if (! $stockUnitId && ! $billingUnitId) {
                $bar->advance();
                continue;
            }

            if ($dryRun) {
                $this->newLine();
                $this->line("[DRY-RUN] {$medication->displayName()}: stock={$defaults['stock_unit']?->code}, billing={$defaults['billing_unit']?->code}");
            } else {
                $medication->updateQuietly([
                    'stock_unit_id' => $medication->stock_unit_id ?? $stockUnitId,
                    'billing_unit_id' => $medication->billing_unit_id ?? $billingUnitId,
                    'dose_unit_id' => $medication->dose_unit_id ?? $doseUnitId,
                ]);
            }

            $updated++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        if ($dryRun) {
            $this->info("DRY-RUN: Would update {$updated} medication(s).");
        } else {
            $this->info("Backfilled units for {$updated} medication(s).");
        }

        return self::SUCCESS;
    }
}
