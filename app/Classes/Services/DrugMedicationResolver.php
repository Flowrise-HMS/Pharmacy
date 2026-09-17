<?php

namespace Modules\Pharmacy\Classes\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\Pharmacy\Models\Drug;
use Modules\Pharmacy\Models\Medication;

/**
 * Resolves a reference Drug to the single Medication (formulary row) that
 * represents it, creating one only when none exists.
 *
 * Every path that turns a Drug into a Medication (prescribing, the Medications
 * create page, "Create from Drug") goes through here so a Drug can never fan
 * out into duplicate catalog rows.
 */
class DrugMedicationResolver
{
    private const LOCK_SECONDS = 10;

    private const LOCK_WAIT_SECONDS = 5;

    public function __construct(
        protected MedicationService $medicationService
    ) {}

    /**
     * Find the Medication already representing this drug, by explicit link
     * first and then by shared RxNorm/NDC code. Returns null rather than an
     * arbitrary row when the drug carries no code at all.
     */
    public function findExisting(Drug $drug): ?Medication
    {
        $linked = Medication::query()->where('drug_id', $drug->id)->first();

        if ($linked) {
            return $linked;
        }

        if (blank($drug->rxnorm_code) && blank($drug->ndc_code)) {
            return null;
        }

        return Medication::query()
            ->where(function (Builder $query) use ($drug): void {
                if (filled($drug->rxnorm_code)) {
                    $query->orWhere('rxnorm_code', $drug->rxnorm_code);
                }

                if (filled($drug->ndc_code)) {
                    $query->orWhere('ndc_code', $drug->ndc_code);
                }
            })
            ->orderBy('created_at')
            ->orderBy('id')
            ->first();
    }

    /**
     * Idempotently return the one Medication for this drug. A brand-new row is
     * created as non-formulary (unpriced, prescription-only) so Pharmacy can
     * review it; callers adopting it into the formulary flip that afterwards.
     *
     * @param  array<string, mixed>  $overrides  Medication/billing fields passed to createFromDrug
     */
    public function resolve(Drug $drug, array $overrides = []): Medication
    {
        // Sibling Drug rows (openFDA, RxNorm-cached, importer) often share a
        // code, so a unique drug_id alone cannot serialize concurrent resolves.
        // Lock on the code instead.
        $lock = Cache::lock($this->lockKey($drug), self::LOCK_SECONDS);

        return $lock->block(self::LOCK_WAIT_SECONDS, function () use ($drug, $overrides): Medication {
            return DB::transaction(function () use ($drug, $overrides): Medication {
                $existing = $this->findExisting($drug);

                if ($existing) {
                    return $this->adopt($existing, $drug);
                }

                try {
                    return $this->medicationService->createFromDrug($drug, $overrides + [
                        'is_formulary' => false,
                        'price' => 0,
                        'requires_prescription' => true,
                    ]);
                } catch (UniqueConstraintViolationException) {
                    return Medication::query()->where('drug_id', $drug->id)->firstOrFail();
                }
            });
        });
    }

    /**
     * Link an unlinked match to this drug. A row already linked to a sibling
     * drug is reused as-is: relinking would just move the duplicate problem.
     */
    protected function adopt(Medication $medication, Drug $drug): Medication
    {
        if ($medication->drug_id === null) {
            $medication->forceFill(['drug_id' => $drug->id])->saveQuietly();
        }

        return $medication;
    }

    protected function lockKey(Drug $drug): string
    {
        $identity = filled($drug->rxnorm_code)
            ? 'rxnorm:'.$drug->rxnorm_code
            : (filled($drug->ndc_code) ? 'ndc:'.$drug->ndc_code : 'drug:'.$drug->id);

        return 'pharmacy:medication-resolve:'.$identity;
    }
}
