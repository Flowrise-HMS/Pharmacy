<?php

namespace Modules\Pharmacy\Filament\Clusters\Pharmacy\Widgets;

use Filament\Widgets\ChartWidget;
use Livewire\Attributes\Reactive;
use Modules\Core\Filament\Concerns\InteractsWithWidgetShield;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\PharmacyCluster;

class FulfillmentTypeDonutChartWidget extends ChartWidget
{
    use InteractsWithWidgetShield;

    protected static ?string $cluster = PharmacyCluster::class;

    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Fulfillment type';

    protected int|string|array $columnSpan = 1;

    #[Reactive]
    public ?array $reportPayload = null;

    protected function getData(): array
    {
        $split = $this->reportPayload['fulfillment_type_split'] ?? ['labels' => [], 'counts' => []];

        if ($split['labels'] === []) {
            return ['labels' => [], 'datasets' => []];
        }

        return [
            'labels' => $split['labels'],
            'datasets' => [[
                'data' => array_map(fn ($v) => (int) $v, $split['counts']),
                'backgroundColor' => ['#16a34a', '#f59e0b', '#6b7280'],
            ]],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return ['responsive' => true, 'maintainAspectRatio' => false];
    }
}
