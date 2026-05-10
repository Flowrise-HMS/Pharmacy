<?php

namespace Modules\Pharmacy\Classes\Support;

use Illuminate\Support\Collection;

final class PharmacyPosTotals
{
    public static function cartSubtotal(Collection $cart): string
    {
        $sum = '0.00';
        foreach ($cart as $item) {
            $unit = sprintf('%.2f', (float) ($item['price'] ?? 0));
            $qty = (string) max(1, (int) ($item['quantity'] ?? 0));
            $line = bcmul($unit, $qty, 2);
            $sum = bcadd($sum, $line, 2);
        }

        return $sum;
    }

    public static function grandTotalAfterDiscount(string $subtotal, string $discount): string
    {
        $d = bccomp($discount, $subtotal, 2) > 0 ? $subtotal : $discount;

        return bcsub($subtotal, $d, 2);
    }

    public static function normalizeMoney(float|string|null $value): string
    {
        if ($value === null || $value === '') {
            return '0.00';
        }

        return sprintf('%.2f', is_numeric($value) ? (float) $value : 0.0);
    }
}
