<?php

namespace Modules\Pharmacy\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum DosageForm: string implements HasColor, HasDescription, HasLabel
{
    case TABLET = 'tablet';
    case CAPSULE = 'capsule';
    case SYRUP = 'syrup';
    case INJECTION = 'injection';
    case OINTMENT = 'ointment';
    case DROPS = 'drops';
    case INHALER = 'inhaler';
    case SUPPOSITORY = 'suppository';

    public function getLabel(): string|Htmlable|null
    {
        return ucfirst($this->value);
    }

    public function getDescription(): string|Htmlable|null
    {
        return match ($this) {
            self::TABLET => 'Solid oral compressed dose form.',
            self::CAPSULE => 'Oral capsule shell dosage form.',
            self::SYRUP => 'Liquid oral sugar-based preparation.',
            self::INJECTION => 'Parenteral medication for injection routes.',
            self::OINTMENT => 'Semi-solid topical formulation.',
            self::DROPS => 'Measured liquid drops for oral/eye/ear use.',
            self::INHALER => 'Medication delivered through inhalation.',
            self::SUPPOSITORY => 'Rectal or vaginal inserted dosage form.',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::TABLET, self::CAPSULE => 'primary',
            self::SYRUP, self::DROPS => 'info',
            self::INJECTION => 'danger',
            self::OINTMENT => 'warning',
            self::INHALER => 'success',
            self::SUPPOSITORY => 'secondary',
        };
    }
}
