<?php

namespace Modules\Pharmacy\Filament\Clusters\Pharmacy\Widgets;

use Filament\Widgets\ChartWidget;
use Livewire\Attributes\Reactive;
use Modules\Core\Filament\Concerns\InteractsWithWidgetShield;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\PharmacyCluster;

class PharmacyRevenueMixDonutChartWidget extends ChartWidget
{
    use InteractsWithWidgetShield;

    protected static ?string $cluster = PharmacyCluster::class;

    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Revenue mix';

    protected int|string|array $columnSpan = 1;

    #[Reactive]
    public ?array $reportPayload = null;

    protected function getData(): array
    {
        $mix = $this->reportPayload['revenue_mix'] ?? ['labels' => [], 'amounts' => []];

        if ($mix['labels'] === []) {
            return ['labels' => [], 'datasets' => []];
        }

        return [
            'labels' => $mix['labels'],
            'datasets' => [[
                'data' => array_map(fn ($v) => (float) $v, $mix['amounts']),
                'backgroundColor' => ['#16a34a', '#3b82f6'],
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
