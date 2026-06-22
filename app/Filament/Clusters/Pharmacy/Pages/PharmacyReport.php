<?php

namespace Modules\Pharmacy\Filament\Clusters\Pharmacy\Pages;

use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\WidgetConfiguration;
use Modules\Core\Models\Branch;
use Modules\Core\Settings\FeatureSettings;
use Modules\Pharmacy\Classes\Services\PharmacyAnalyticsService;
use Modules\Pharmacy\Data\PharmacyReportCriteria;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\PharmacyCluster;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Widgets\FulfillmentTypeDonutChartWidget;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Widgets\LowStockItemsTableWidget;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Widgets\MarAdherenceDonutChartWidget;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Widgets\OutsidePurchaseDispensesTableWidget;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Widgets\PharmacyDispensesTrendChartWidget;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Widgets\PharmacyRevenueMixDonutChartWidget;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Widgets\PharmacySalesByBranchChartWidget;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Widgets\PharmacySalesTrendChartWidget;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Widgets\PrescriptionStatusDonutChartWidget;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Widgets\RecentDispensesTableWidget;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Widgets\RecentPosSalesTableWidget;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Widgets\StockMovementReasonDonutChartWidget;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Widgets\TopDispensedMedicationsBarChartWidget;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Widgets\TopSellingMedicationsBarChartWidget;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Widgets\TopSellingMedicationsTableWidget;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Widgets\TopSellingServicesBarChartWidget;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Widgets\TopSellingServicesTableWidget;

class PharmacyReport extends Page
{
    use HasPageShield;

    protected static ?string $cluster = PharmacyCluster::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartBar;

    protected static ?string $navigationLabel = 'Pharmacy report';

    protected static ?string $title = 'Pharmacy report';

    protected string $view = 'pharmacy::filament.clusters.pharmacy.pages.pharmacy-report';

    public ?string $preset = 'today';

    public ?string $startDate = null;

    public ?string $endDate = null;

    public ?string $branchId = null;

    public ?string $lineKind = 'all';

    public ?string $channel = 'all';

    /**
     * @var array<string, mixed>
     */
    public array $report = [];

    public function mount(): void
    {
        $this->branchId = request()->query('branch_id');
        $this->lineKind = (string) request()->query('line_kind', 'all');
        $this->channel = (string) request()->query('channel', 'all');

        $hasCustomDates = request()->has('start_date') && request()->has('end_date');
        $this->preset = $hasCustomDates ? 'custom' : (string) request()->query('preset', 'today');

        $criteria = PharmacyReportCriteria::fromRequest(request()->query());

        $this->startDate = $criteria->startDate->toDateString();
        $this->endDate = $criteria->endDate->toDateString();

        $this->loadReport();
    }

    /**
     * @return array<string, string>
     */
    public function getBranchOptionsProperty(): array
    {
        return Branch::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * @return array<string, string>
     */
    public function getLineKindOptionsProperty(): array
    {
        return [
            'all' => __('All line kinds'),
            'medication' => __('Medications'),
            'service' => __('Services'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function getChannelOptionsProperty(): array
    {
        return [
            'all' => __('All channels'),
            'pos' => __('POS'),
            'clinical' => __('Clinical'),
        ];
    }

    public function presetUrl(string $preset): string
    {
        return static::getUrl([
            'preset' => $preset,
            'branch_id' => $this->branchId,
            'line_kind' => $this->lineKind,
            'channel' => $this->channel,
        ]);
    }

    /**
     * @return array<class-string|int, class-string|WidgetConfiguration>
     */
    protected function getFooterWidgets(): array
    {
        $payload = ['reportPayload' => $this->report];

        return [
            PharmacyRevenueMixDonutChartWidget::make($payload),
            PharmacySalesTrendChartWidget::make($payload),
            PharmacyDispensesTrendChartWidget::make($payload),
            PharmacySalesByBranchChartWidget::make($payload),
            FulfillmentTypeDonutChartWidget::make($payload),
            PrescriptionStatusDonutChartWidget::make($payload),
            MarAdherenceDonutChartWidget::make($payload),
            TopDispensedMedicationsBarChartWidget::make($payload),
            TopSellingMedicationsBarChartWidget::make($payload),
            TopSellingServicesBarChartWidget::make($payload),
            StockMovementReasonDonutChartWidget::make($payload),
        ];
    }

    /**
     * @return array<class-string|int, class-string|WidgetConfiguration>
     */
    public function getReportTableWidgets(): array
    {
        $payload = ['reportPayload' => $this->report];

        return [
            LowStockItemsTableWidget::make($payload),
            RecentDispensesTableWidget::make($payload),
            TopSellingMedicationsTableWidget::make($payload),
            TopSellingServicesTableWidget::make($payload),
            RecentPosSalesTableWidget::make($payload),
            OutsidePurchaseDispensesTableWidget::make($payload),
        ];
    }

    protected function loadReport(): void
    {
        $this->report = app(PharmacyAnalyticsService::class)->buildFromCriteria($this->buildCriteria());
    }

    protected function buildCriteria(): PharmacyReportCriteria
    {
        return PharmacyReportCriteria::fromRequest([
            'preset' => $this->preset,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'branch_id' => $this->branchId,
            'line_kind' => $this->lineKind,
            'channel' => $this->channel,
        ]);
    }

    public static function shouldRegisterNavigation(): bool
    {
        try {
            return app(FeatureSettings::class)->pharmacy_reports_enabled;
        } catch (\Throwable) {
            return true;
        }
    }
}
