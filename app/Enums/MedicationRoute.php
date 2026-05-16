<?php

namespace Modules\Pharmacy\Enums;

use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum MedicationRoute: string implements HasLabel
{
    case PO = 'po';
    case IV = 'iv';
    case IM = 'im';
    case SC = 'sc';
    case SL = 'sl';
    case PR = 'pr';
    case TOPICAL = 'topical';
    case INHALATION = 'inhalation';
    case OTHERS = 'others';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::PO => 'Oral (PO)',
            self::IV => 'Intravenous (IV)',
            self::IM => 'Intramuscular (IM)',
            self::SC => 'Subcutaneous (SC)',
            self::SL => 'Sublingual (SL)',
            self::PR => 'Rectal (PR)',
            self::TOPICAL => 'Topical',
            self::INHALATION => 'Inhalation',
            self::OTHERS => 'Other',
        };
    }
}
