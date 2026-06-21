<?php

namespace Modules\Pharmacy\Filament\Clusters\Pharmacy\Widgets;

use Filament\Widgets\ChartWidget;
use Livewire\Attributes\Reactive;
use Modules\Core\Filament\Concerns\InteractsWithWidgetShield;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\PharmacyCluster;

class TopSellingServicesBarChartWidget extends ChartWidget
{
    use InteractsWithWidgetShield;

    protected static ?string $cluster = PharmacyCluster::class;

    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Top selling services';

    protected int|string|array $columnSpan = 1;

    #[Reactive]
    public ?array $reportPayload = null;

    protected function getData(): array
    {
        $rows = $this->reportPayload['top_selling_services'] ?? [];

        if ($rows === []) {
            return ['labels' => [], 'datasets' => []];
        }

        return [
            'labels' => array_column($rows, 'label'),
            'datasets' => [[
                'label' => __('Revenue'),
                'data' => array_map(fn (array $row) => (float) ($row['revenue'] ?? 0), $rows),
                'backgroundColor' => '#3b82f6',
            ]],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'responsive' => true,
            'maintainAspectRatio' => false,
            'scales' => ['x' => ['beginAtZero' => true]],
        ];
    }
}
