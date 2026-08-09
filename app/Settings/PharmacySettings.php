<?php

namespace Modules\Pharmacy\Settings;

use Spatie\LaravelSettings\Settings;

class PharmacySettings extends Settings
{
    public bool $pos_collect_payment = true;

    public string $pos_default_charge_mode = 'charge_account';

    /** @var array<int, string> */
    public array $pos_payment_methods = ['cash', 'card', 'bank_transfer', 'mobile_money'];

    public bool $external_drug_lookup = false;

    public int $default_reorder_point = 10;

    public bool $block_controlled_on_pos = true;

    public bool $guest_checkout_enabled = true;

    public bool $services_tab_enabled = true;

    public static function group(): string
    {
        return 'pharmacy';
    }
}
