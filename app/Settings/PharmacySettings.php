<?php

namespace Modules\Pharmacy\Settings;

use Spatie\LaravelSettings\Settings;

class PharmacySettings extends Settings
{
    /**
     * cashier_chooses | pay_now | charge_account (see Modules\Core\Enums\PosCheckoutMode).
     * Organization and branch settings can override it.
     */
    public string $pos_checkout_mode = 'cashier_chooses';

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
