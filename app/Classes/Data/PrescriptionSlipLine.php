<?php

namespace Modules\Pharmacy\Classes\Data;

use App\Models\User;
use Modules\Clinical\Models\RequestItem;
use Modules\Pharmacy\Models\Dispense;

readonly class PrescriptionSlipLine
{
    /**
     * @param  array<int, array{label: string, value: string}>  $sigRows
     */
    public function __construct(
        public RequestItem $item,
        public string $sigLine,
        public array $sigRows,
        public ?User $prescriber,
        public ?Dispense $outsideDispense,
    ) {}
}
