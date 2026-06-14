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
    case SUSPENSION = 'suspension';
    case SOLUTION = 'solution';
    case INJECTION = 'injection';
    case OINTMENT = 'ointment';
    case CREAM = 'cream';
    case GEL = 'gel';
    case LOTION = 'lotion';
    case DROPS = 'drops';
    case SPRAY = 'spray';
    case INHALER = 'inhaler';
    case AEROSOL = 'aerosol';
    case SUPPOSITORY = 'suppository';
    case POWDER = 'powder';
    case GRANULES = 'granules';
    case SACHET = 'sachet';
    case PATCH = 'patch';
    case LOZENGE = 'lozenge';
    case ENEMA = 'enema';
    case MOUTHWASH = 'mouthwash';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::GRANULES => 'Granules',
            self::MOUTHWASH => 'Mouthwash',
            default => ucfirst($this->value),
        };
    }

    public function getDescription(): string|Htmlable|null
    {
        return match ($this) {
            self::TABLET => 'Solid oral compressed dose form.',
            self::CAPSULE => 'Oral capsule shell dosage form.',
            self::SYRUP => 'Sweetened liquid oral preparation.',
            self::SUSPENSION => 'Liquid with undissolved solid particles.',
            self::SOLUTION => 'Homogeneous liquid dosage form.',
            self::INJECTION => 'Parenteral medication for injection.',
            self::OINTMENT => 'Semi-solid oily topical formulation.',
            self::CREAM => 'Semi-solid water-based topical formulation.',
            self::GEL => 'Transparent or translucent semi-solid.',
            self::LOTION => 'Liquid topical preparation for skin.',
            self::DROPS => 'Measured liquid drops (eye/ear/nasal/oral).',
            self::SPRAY => 'Medication delivered as fine mist.',
            self::INHALER => 'Medication delivered through inhalation.',
            self::AEROSOL => 'Pressurized dosage form for inhalation/topical use.',
            self::SUPPOSITORY => 'Rectal or vaginal inserted dosage form.',
            self::POWDER => 'Dry powdered medication.',
            self::GRANULES => 'Small grain-like particles for oral use.',
            self::SACHET => 'Powdered medication in single-dose packet.',
            self::PATCH => 'Transdermal patch for skin absorption.',
            self::LOZENGE => 'Solid dosage form that dissolves in mouth.',
            self::ENEMA => 'Rectal liquid medication.',
            self::MOUTHWASH => 'Oral rinse for mouth and throat.',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::TABLET, self::CAPSULE, self::LOZENGE => 'primary',
            self::SYRUP, self::SUSPENSION, self::SOLUTION, self::DROPS => 'info',
            self::INJECTION => 'danger',
            self::OINTMENT, self::CREAM, self::GEL, self::LOTION => 'warning',
            self::INHALER, self::AEROSOL, self::SPRAY => 'success',
            self::SUPPOSITORY, self::ENEMA => 'secondary',
            default => 'gray',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
