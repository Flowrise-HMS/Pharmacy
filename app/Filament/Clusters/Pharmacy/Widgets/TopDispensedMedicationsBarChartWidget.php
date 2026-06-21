<?php

namespace Modules\Pharmacy\Filament\Clusters\Pharmacy\Widgets;

use Filament\Widgets\ChartWidget;
use Livewire\Attributes\Reactive;
use Modules\Core\Filament\Concerns\InteractsWithWidgetShield;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\PharmacyCluster;

class TopDispensedMedicationsBarChartWidget extends ChartWidget
{
    use InteractsWithWidgetShield;

    protected static ?string $cluster = PharmacyCluster::class;

    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Top dispensed medications';

    protected int|string|array $columnSpan = 1;

    #[Reactive]
    public ?array $reportPayload = null;

    protected function getData(): array
    {
        $data = $this->reportPayload['top_dispensed_medications'] ?? ['labels' => [], 'quantities' => []];

        if ($data['labels'] === []) {
            return ['labels' => [], 'datasets' => []];
        }

        return [
            'labels' => $data['labels'],
            'datasets' => [[
                'label' => __('Quantity'),
                'data' => array_map(fn ($v) => (int) $v, $data['quantities']),
                'backgroundColor' => '#8b5cf6',
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
