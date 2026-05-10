<?php

namespace Modules\Pharmacy\Providers;

use Modules\Clinical\Models\RequestItem;
use Modules\Core\Contracts\StockProviderContract;
use Modules\Pharmacy\Classes\Services\StockService;
use Modules\Pharmacy\Models\Dispense;
use Nwidart\Modules\Support\ModuleServiceProvider;

class PharmacyServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Pharmacy';

    protected string $nameLower = 'pharmacy';

    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->app->bind(StockProviderContract::class, StockService::class);
    }

    public function boot(): void
    {
        parent::boot();

        RequestItem::resolveRelationUsing('dispenses', function (RequestItem $requestItem) {
            return $requestItem->hasMany(Dispense::class);
        });
    }
}
