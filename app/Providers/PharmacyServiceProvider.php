<?php

namespace Modules\Pharmacy\Providers;

use Modules\Core\Contracts\StockProviderContract;
use Modules\Pharmacy\Classes\Services\StockService;
use Modules\Pharmacy\Console\BackfillMedicationBillingServicesCommand;
use Modules\Pharmacy\Console\ImportFDANdcDrugData;
use Modules\Pharmacy\Models\Dispense;
use Modules\Pharmacy\Models\Medication;
use Modules\Pharmacy\Observers\MedicationObserver;
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

        if (class_exists(\Modules\Clinical\Models\RequestItem::class)) {
            \Modules\Clinical\Models\RequestItem::resolveRelationUsing('dispenses', function ($requestItem) {
                return $requestItem->hasMany(Dispense::class);
            });
        }

        $this->registerCommands();
    }

     /**
     * Register commands in the format of Command::class
     */
    protected function registerCommands(): void
    {
        $this->commands([
            ImportFDANdcDrugData::class,
            BackfillMedicationBillingServicesCommand::class,
        ]);
    }
}
