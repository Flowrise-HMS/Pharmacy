<?php

namespace Modules\Pharmacy\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum StockMovementReason: string implements HasColor, HasDescription, HasLabel
{
    case DISPENSE = 'dispense';
    case RECEIVE = 'receive';
    case ADJUST = 'adjust';
    case TRANSFER = 'transfer';

    public function getLabel(): string|Htmlable|null
    {
        return ucfirst($this->value);
    }

    public function getDescription(): string|Htmlable|null
    {
        return match ($this) {
            self::DISPENSE => 'Stock decreased due to medication dispensing.',
            self::RECEIVE => 'Stock increased from procurement or receipt.',
            self::ADJUST => 'Manual correction after reconciliation.',
            self::TRANSFER => 'Movement between branches or storage points.',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::DISPENSE => 'warning',
            self::RECEIVE => 'success',
            self::ADJUST => 'danger',
            self::TRANSFER => 'info',
        };
    }
}
