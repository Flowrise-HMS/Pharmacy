<?php

namespace Modules\Pharmacy\Enums;

use Filament\Support\Contracts\HasLabel;

enum DispenseFulfillmentType: string implements HasLabel
{
    case IN_HOUSE = 'in_house';
    case OUTSIDE_PURCHASE = 'outside_purchase';
    case SUPPLY_ONLY = 'supply_only';

    public function getLabel(): string
    {
        return match ($this) {
            self::IN_HOUSE => 'In-house dispense',
            self::OUTSIDE_PURCHASE => 'Outside purchase',
            self::SUPPLY_ONLY => 'Supply only',
        };
    }
}
