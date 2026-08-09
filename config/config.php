<?php

return [
    'name' => 'Pharmacy',
    'permissions' => [
        'order_prescription_medication' => 'Order Prescription Medication',
        'administer_medication' => 'Record medication administration (MAR)',
        'dispense_medication' => 'Dispense medications (pharmacy)',
        'manage_pharmacy_settings' => 'ManagePharmacySettings',
    ],
    'enable_external_drug_lookup' => env('PHARMACY_ENABLE_EXTERNAL_DRUG_LOOKUP', false),
];
