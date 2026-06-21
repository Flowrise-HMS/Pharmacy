<?php

namespace Modules\Pharmacy\Filament\Clusters\Pharmacy\Widgets;

use Filament\Widgets\ChartWidget;
use Livewire\Attributes\Reactive;
use Modules\Core\Filament\Concerns\InteractsWithWidgetShield;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\PharmacyCluster;

class PharmacyDispensesTrendChartWidget extends ChartWidget
{
    use InteractsWithWidgetShield;

    protected static ?string $cluster = PharmacyCluster::class;

    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Daily dispenses';

    protected int|string|array $columnSpan = 1;

    #[Reactive]
    public ?array $reportPayload = null;

    protected function getData(): array
    {
        $trend = $this->reportPayload['dispenses_trend'] ?? ['labels' => [], 'counts' => []];

        if ($trend['labels'] === []) {
            return ['labels' => [], 'datasets' => []];
        }

        return [
            'labels' => $trend['labels'],
            'datasets' => [[
                'label' => __('Dispenses'),
                'data' => array_map(fn ($v) => (int) $v, $trend['counts']),
                'borderColor' => '#f59e0b',
                'backgroundColor' => 'rgba(245, 158, 11, 0.1)',
                'tension' => 0.3,
                'fill' => false,
            ]],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'scales' => ['y' => ['beginAtZero' => true]],
        ];
    }
}
