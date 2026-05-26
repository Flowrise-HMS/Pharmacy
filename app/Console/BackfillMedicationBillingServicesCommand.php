<?php

namespace Modules\Pharmacy\Console;

use Illuminate\Console\Command;
use Modules\Pharmacy\Classes\Services\MedicationBillingSyncService;
use Modules\Pharmacy\Models\Medication;

class BackfillMedicationBillingServicesCommand extends Command
{
    protected $signature = 'pharmacy:backfill-medication-services
        {--dry-run : Preview changes without executing}
        {--default-price=0 : Default price for medications without price}';

    protected $description = 'Backfill billing services for medications missing a linked service';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $defaultPrice = (float) $this->option('default-price');

        $medications = Medication::whereDoesntHave('service')->get();

        if ($medications->isEmpty()) {
            $this->info('All medications already have a billing service.');

            return self::SUCCESS;
        }

        $this->line("Found {$medications->count()} medication(s) without a billing service.");

        $bar = $this->output->createProgressBar($medications->count());
        $bar->start();

        foreach ($medications as $medication) {
            if ($dryRun) {
                $this->newLine();
                $this->line("[DRY-RUN] Would create billing service for: {$medication->displayName()} (ID: {$medication->id})");
            } else {
                app(MedicationBillingSyncService::class)->ensureBillingService($medication, [
                    'price' => $defaultPrice,
                ]);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        if (! $dryRun) {
            $this->info("Backfilled {$medications->count()} medication(s) successfully.");
        }

        return self::SUCCESS;
    }
}
