<?php

namespace Modules\Pharmacy\Classes\Support;

use Modules\Core\Models\Unit;
use Modules\Pharmacy\Models\Medication;

class MedicationQuantityValidator
{
    public function assertStockQuantity(Medication $medication, int|float $quantity, string $context = 'stock'): void
    {
        $unit = match ($context) {
            'stock' => $medication->stockUnit,
            'billing' => $medication->billingUnit,
            'dose' => $medication->doseUnit,
            default => $medication->stockUnit,
        };

        if (! $unit) {
            return;
        }

        if (! $unit->is_fractional && is_float($quantity)) {
            throw new \InvalidArgumentException(
                "{$unit->label} quantities must be whole numbers. Got {$quantity}."
            );
        }

        if ($quantity < 0) {
            throw new \InvalidArgumentException("Quantity cannot be negative. Got {$quantity}.");
        }
    }

    public function assertBillingQuantity(int $quantity): void
    {
        if ($quantity < 1) {
            throw new \InvalidArgumentException("Billing quantity must be at least 1. Got {$quantity}.");
        }
    }

    public function assertDoseDoesNotExceedRemaining(int $doseAmount, int $remaining): void
    {
        if ($doseAmount > $remaining) {
            throw new \InvalidArgumentException(
                "Dose amount {$doseAmount} exceeds remaining {$remaining} dose(s)."
            );
        }
    }

    public function assertUnitsMatch(?Unit $expected, ?Unit $actual, string $label): void
    {
        if ($expected && $actual && $expected->id !== $actual->id) {
            throw new \InvalidArgumentException(
                "{$label}: expected unit '{$expected->label}' but got '{$actual->label}'."
            );
        }
    }
}
