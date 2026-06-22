<?php

namespace Modules\Pharmacy\Classes\Support;

use Modules\Core\Models\Unit;
use Modules\Pharmacy\Enums\DosageForm;

class UnitResolver
{
    /** @return array{stock_unit: ?Unit, billing_unit: ?Unit, dose_unit: ?Unit} */
    public function defaultsForDosageForm(?DosageForm $dosageForm): array
    {
        if (! $dosageForm) {
            return ['stock_unit' => null, 'billing_unit' => null, 'dose_unit' => null];
        }

        return match ($dosageForm) {
            DosageForm::TABLET, DosageForm::LOZENGE => $this->resolve('tablet', 'tablet', 'tablet'),
            DosageForm::CAPSULE => $this->resolve('capsule', 'capsule', 'capsule'),
            DosageForm::SYRUP, DosageForm::SUSPENSION, DosageForm::SOLUTION,
            DosageForm::DROPS => $this->resolve('bottle', 'bottle', 'ml'),
            DosageForm::INJECTION => $this->resolve('vial', 'vial', 'ml'),
            DosageForm::OINTMENT, DosageForm::CREAM, DosageForm::GEL, DosageForm::LOTION => $this->resolve('tube', 'tube', 'g'),
            DosageForm::SPRAY, DosageForm::INHALER, DosageForm::AEROSOL => $this->resolve('each', 'each', 'dose'),
            DosageForm::SUPPOSITORY, DosageForm::PATCH => $this->resolve('each', 'each', 'each'),
            DosageForm::POWDER, DosageForm::GRANULES, DosageForm::SACHET => $this->resolve('sachet', 'sachet', 'g'),
            DosageForm::ENEMA, DosageForm::MOUTHWASH => $this->resolve('bottle', 'bottle', 'ml'),
        };
    }

    public function formatQuantity(int|float $amount, ?Unit $unit): string
    {
        if (! $unit) {
            return (string) $amount;
        }

        $formatted = is_float($amount)
            ? rtrim(rtrim(number_format($amount, 4), '0'), '.')
            : (string) $amount;

        return "{$formatted} {$unit->label}";
    }

    public function formatQuantityShort(int|float $amount, ?Unit $unit): string
    {
        if (! $unit) {
            return (string) $amount;
        }

        return "{$amount} {$unit->code}";
    }

    public function unitLabel(?Unit $unit): string
    {
        return $unit?->label ?? 'units';
    }

    /** @return array{stock_unit: ?Unit, billing_unit: ?Unit, dose_unit: ?Unit} */
    private function resolve(string $stockCode, string $billingCode, string $doseCode): array
    {
        $units = Unit::whereIn('code', [$stockCode, $billingCode, $doseCode])
            ->get()
            ->keyBy('code');

        return [
            'stock_unit' => $units->get($stockCode),
            'billing_unit' => $units->get($billingCode),
            'dose_unit' => $units->get($doseCode),
        ];
    }
}
