<?php

namespace Modules\Pharmacy\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum MedicationFrequency: string implements HasColor, HasDescription, HasLabel
{
    case QD = 'qd';
    case BID = 'bid';
    case TID = 'tid';
    case QID = 'qid';
    case Q4H = 'q4h';
    case Q6H = 'q6h';
    case Q8H = 'q8h';
    case Q12H = 'q12h';
    case PRN = 'prn';
    case STAT = 'stat';
    case ONCE = 'once';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::QD => 'Once Daily (QD)',
            self::BID => 'Twice Daily (BID)',
            self::TID => 'Three Times Daily (TID)',
            self::QID => 'Four Times Daily (QID)',
            self::Q4H => 'Every 4 Hours (Q4H)',
            self::Q6H => 'Every 6 Hours (Q6H)',
            self::Q8H => 'Every 8 Hours (Q8H)',
            self::Q12H => 'Every 12 Hours (Q12H)',
            self::PRN => 'As Needed (PRN)',
            self::STAT => 'Immediately (STAT)',
            self::ONCE => 'One Time (Once)',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::QD => 'primary',
            self::BID, self::TID, self::QID => 'info',
            self::Q4H, self::Q6H, self::Q8H, self::Q12H => 'warning',
            self::PRN => 'secondary',
            self::STAT => 'danger',
            self::ONCE => 'gray',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function getDescription(): ?string
    {
        return match ($this) {
            self::QD => 'Administered once every 24 hours',
            self::BID => 'Administered twice per day (morning and evening)',
            self::TID => 'Administered three times per day',
            self::QID => 'Administered four times per day',
            self::Q4H => 'Administered every 4 hours around the clock',
            self::Q6H => 'Administered every 6 hours around the clock',
            self::Q8H => 'Administered every 8 hours around the clock',
            self::Q12H => 'Administered every 12 hours (twice daily)',
            self::PRN => 'Administered as needed based on patient condition',
            self::STAT => 'Administered immediately as a one-time dose',
            self::ONCE => 'Administered one time only',
        };
    }

    public function timesPerDay(): ?int
    {
        return match ($this) {
            self::QD => 1,
            self::BID => 2,
            self::TID => 3,
            self::QID => 4,
            self::Q4H => 6,
            self::Q6H => 4,
            self::Q8H => 3,
            self::Q12H => 2,
            self::PRN => null,
            self::STAT => 1,
            self::ONCE => 1,
        };
    }

    public function hoursInterval(): ?int
    {
        return match ($this) {
            self::Q4H => 4,
            self::Q6H => 6,
            self::Q8H => 8,
            self::Q12H => 12,
            default => null,
        };
    }

    public function isIntervalBased(): bool
    {
        return $this->hoursInterval() !== null;
    }
}
