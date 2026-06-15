<?php

namespace Modules\Pharmacy\Classes\Data;

use Modules\Clinical\Models\RequestItem;
use Modules\Pharmacy\Models\Medication;

readonly class PharmacyPosPrescriptionLine
{
    public function __construct(
        public RequestItem $requestItem,
        public ?Medication $medication,
        public int $stockQuantity,
        public bool $hasMedicationCatalogRow,
        public bool $controlledSubstance,
        public bool $canDispenseInHouse,
        public bool $canRecordOutsidePurchase,
        public bool $canReprintExternalSlip,
        public ?string $blockedReason,
        public string $sigSummary,
        public string $stockLabel,
    ) {}
}
