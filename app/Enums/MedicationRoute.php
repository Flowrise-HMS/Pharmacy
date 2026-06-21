<?php

namespace Modules\Pharmacy\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum MedicationRoute: string implements HasColor, HasDescription, HasLabel
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

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::PO => 'primary',
            self::IV => 'danger',
            self::IM => 'warning',
            self::SC => 'info',
            self::SL => 'success',
            self::PR => 'secondary',
            self::TOPICAL => 'warning',
            self::INHALATION => 'info',
            self::OTHERS => 'gray',
        };
    }

    public function getDescription(): ?string
    {
        return match ($this) {
            self::PO => 'Administered by mouth in tablet, capsule, or liquid form',
            self::IV => 'Injected directly into a vein for immediate systemic effect',
            self::IM => 'Injected into a muscle for slower absorption',
            self::SC => 'Injected under the skin layers',
            self::SL => 'Placed under the tongue for rapid absorption',
            self::PR => 'Inserted into the rectum for local or systemic effect',
            self::TOPICAL => 'Applied directly to the skin or mucous membranes',
            self::INHALATION => 'Breathed in through the mouth or nose',
            self::OTHERS => 'Any other route not covered by standard options',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

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
