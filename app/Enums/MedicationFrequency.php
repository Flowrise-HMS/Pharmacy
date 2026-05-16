<?php

namespace Modules\Pharmacy\Enums;

use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum MedicationFrequency: string implements HasLabel
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
}
