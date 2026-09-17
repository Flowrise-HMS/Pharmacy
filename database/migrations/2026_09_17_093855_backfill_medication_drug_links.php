<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Link existing Medications to the Drug they were created from. The link was
 * never stored, so match on rxnorm_code first, then ndc_code. Each Drug may be
 * claimed by only one Medication (unique drug_id); duplicates that lose the
 * race stay unlinked and are handled by pharmacy:merge-duplicate-medications.
 */
return new class extends Migration
{
    public function up(): void
    {
        $claimedDrugIds = DB::table('medications')
            ->whereNotNull('drug_id')
            ->pluck('drug_id')
            ->flip()
            ->all();

        DB::table('medications')
            ->whereNull('drug_id')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'rxnorm_code', 'ndc_code'])
            ->each(function (object $medication) use (&$claimedDrugIds): void {
                $drugId = $this->findUnclaimedDrugId('rxnorm_code', $medication->rxnorm_code, $claimedDrugIds)
                    ?? $this->findUnclaimedDrugId('ndc_code', $medication->ndc_code, $claimedDrugIds);

                if ($drugId === null) {
                    return;
                }

                DB::table('medications')
                    ->where('id', $medication->id)
                    ->update(['drug_id' => $drugId]);

                $claimedDrugIds[$drugId] = true;
            });
    }

    /**
     * Data-only backfill; the columns are dropped by the schema migration.
     */
    public function down(): void {}

    /**
     * @param  array<string, true>  $claimedDrugIds
     */
    private function findUnclaimedDrugId(string $column, ?string $code, array $claimedDrugIds): ?string
    {
        if (blank($code)) {
            return null;
        }

        return DB::table('drugs')
            ->where($column, $code)
            ->when($claimedDrugIds !== [], fn ($query) => $query->whereNotIn('id', array_keys($claimedDrugIds)))
            ->orderBy('is_cached_external')
            ->orderBy('created_at')
            ->orderBy('id')
            ->value('id');
    }
};
