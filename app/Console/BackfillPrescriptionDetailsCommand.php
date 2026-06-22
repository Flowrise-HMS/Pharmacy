<?php

namespace Modules\Pharmacy\Console;

use Illuminate\Console\Command;
use Modules\Clinical\Classes\Services\MedicationFulfillmentPolicy;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Models\RequestItem;
use Modules\Pharmacy\Classes\Services\PrescriptionScheduleCalculator;
use Modules\Pharmacy\Models\PrescriptionDetail;

class BackfillPrescriptionDetailsCommand extends Command
{
    protected $signature = 'pharmacy:backfill-prescription-details
        {--dry-run : Preview changes without executing}';

    protected $description = 'Backfill prescription details for medication request items missing SIG data';

    public function handle(
        MedicationFulfillmentPolicy $policy,
        PrescriptionScheduleCalculator $calculator,
    ): int {
        $dryRun = (bool) $this->option('dry-run');

        $items = RequestItem::query()
            ->whereDoesntHave('prescriptionDetail')
            ->whereHas('service.category', fn ($q) => $q->where('code', 'MED'))
            ->with(['serviceRequest.encounter', 'service'])
            ->get();

        if ($items->isEmpty()) {
            $this->info('No medication request items need backfill.');

            return self::SUCCESS;
        }

        $this->line("Found {$items->count()} item(s) to backfill.");
        $updated = 0;

        foreach ($items as $item) {
            /** @var Encounter|null $encounter */
            $encounter = $item->serviceRequest?->encounter;
            $context = $policy->defaultAdministrationContext($encounter);
            $schedule = $calculator->compute([
                'frequency' => 'qd',
                'duration_days' => 1,
                'prn' => false,
                'course_started_at' => now(),
            ]);

            $payload = [
                'request_item_id' => $item->id,
                'dosage' => $item->service?->name,
                'frequency' => 'qd',
                'duration_days' => 1,
                'prn' => false,
                'instructions' => 'Backfilled — physician review required',
                'administration_context' => $context,
                'course_started_at' => $schedule['course_started_at'],
                'course_end_at' => $schedule['course_end_at'],
                'total_administrations' => $schedule['total_administrations'],
            ];

            if ($dryRun) {
                $this->line("Would backfill item {$item->id} ({$item->service?->name})");
            } else {
                PrescriptionDetail::create($payload);
            }

            $updated++;
        }

        $this->info($dryRun
            ? "Dry run complete — {$updated} item(s) would be updated."
            : "Backfilled {$updated} prescription detail(s).");

        return self::SUCCESS;
    }
}
