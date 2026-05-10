<?php

namespace Modules\Pharmacy\Filament;

use Coolsam\Modules\Concerns\ModuleFilamentPlugin;
use Filament\Contracts\Plugin;
use Filament\Panel;

class PharmacyPlugin implements Plugin
{
    use ModuleFilamentPlugin;

    public function getModuleName(): string
    {
        return 'Pharmacy';
    }

    public function getId(): string
    {
        return 'pharmacy';
    }

    public function boot(Panel $panel): void
    {
        // TODO: Implement boot() method.
    }
}
