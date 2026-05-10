<?php

namespace Modules\Pharmacy\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum ControlledSchedule: string implements HasColor, HasDescription, HasLabel
{
    case SCHEDULE_2 = 'schedule_2';
    case SCHEDULE_3 = 'schedule_3';
    case SCHEDULE_4 = 'schedule_4';
    case SCHEDULE_5 = 'schedule_5';

    public function getLabel(): string|Htmlable|null
    {
        return strtoupper(str_replace('_', ' ', $this->value));
    }

    public function getDescription(): string|Htmlable|null
    {
        return match ($this) {
            self::SCHEDULE_2 => 'High potential for abuse with accepted medical use.',
            self::SCHEDULE_3 => 'Moderate to low dependence risk.',
            self::SCHEDULE_4 => 'Low potential for abuse and dependence.',
            self::SCHEDULE_5 => 'Lowest controlled risk category.',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::SCHEDULE_2 => 'danger',
            self::SCHEDULE_3 => 'warning',
            self::SCHEDULE_4 => 'info',
            self::SCHEDULE_5 => 'gray',
        };
    }
}
