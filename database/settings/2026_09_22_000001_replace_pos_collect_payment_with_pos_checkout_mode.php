<?php

use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * One tri-state checkout mode replaces the "allow pay-now" flag and the
 * "default charge mode": pay-now switched off becomes "send to billing only",
 * otherwise the cashier chooses.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->inGroup('pharmacy', function ($blueprint): void {
            $collect = true;

            if ($this->migrator->exists('pharmacy.pos_collect_payment')) {
                $payload = DB::table('settings')->where('group', 'pharmacy')->where('name', 'pos_collect_payment')->value('payload');
                $collect = (bool) json_decode((string) $payload, true);
            }

            if (! $this->migrator->exists('pharmacy.pos_checkout_mode')) {
                $blueprint->add('pos_checkout_mode', $collect ? 'cashier_chooses' : 'charge_account');
            }

            foreach (['pos_collect_payment', 'pos_default_charge_mode'] as $key) {
                if ($this->migrator->exists("pharmacy.{$key}")) {
                    $blueprint->delete($key);
                }
            }
        });
    }

    public function down(): void
    {
        $this->migrator->inGroup('pharmacy', function ($blueprint): void {
            $mode = DB::table('settings')->where('group', 'pharmacy')->where('name', 'pos_checkout_mode')->value('payload');

            $blueprint->add('pos_collect_payment', json_decode((string) $mode, true) !== 'charge_account');
            $blueprint->add('pos_default_charge_mode', 'charge_account');
            $blueprint->delete('pos_checkout_mode');
        });
    }
};
