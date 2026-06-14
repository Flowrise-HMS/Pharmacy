<?php

namespace Modules\Pharmacy\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum AdministrationContext: string implements HasColor, HasDescription, HasLabel
{
    case IN_FACILITY = 'in_facility';
    case TAKE_HOME = 'take_home';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::IN_FACILITY => 'In facility (staff MAR)',
            self::TAKE_HOME => 'Take home (patient self-admin)',
        };
    }

    public function getDescription(): ?string
    {
        return match ($this) {
            self::IN_FACILITY => 'Nursing staff record each dose during an active encounter',
            self::TAKE_HOME => 'Pharmacy dispenses once; patient self-administers at home',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::IN_FACILITY => 'primary',
            self::TAKE_HOME => 'info',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
