<?php

return [
    'name' => 'Pharmacy',
    'permissions' => [
        'order_prescription_medication' => 'Order Prescription Medication',
    ],
    'enable_external_drug_lookup' => env('PHARMACY_ENABLE_EXTERNAL_DRUG_LOOKUP', false),
];
