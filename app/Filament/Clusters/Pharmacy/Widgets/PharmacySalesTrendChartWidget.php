<?php

namespace Modules\Pharmacy\Filament\Clusters\Pharmacy\Widgets;

use Filament\Widgets\ChartWidget;
use Livewire\Attributes\Reactive;
use Modules\Core\Filament\Concerns\InteractsWithWidgetShield;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\PharmacyCluster;

class PharmacySalesTrendChartWidget extends ChartWidget
{
    use InteractsWithWidgetShield;

    protected static ?string $cluster = PharmacyCluster::class;

    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Daily medication vs service sales';

    protected int|string|array $columnSpan = 1;

    #[Reactive]
    public ?array $reportPayload = null;

    protected function getData(): array
    {
        $trend = $this->reportPayload['daily_sales_trend'] ?? [
            'labels' => [],
            'medication_amounts' => [],
            'service_amounts' => [],
        ];

        if ($trend['labels'] === []) {
            return ['labels' => [], 'datasets' => []];
        }

        return [
            'labels' => $trend['labels'],
            'datasets' => [
                [
                    'label' => __('Medications'),
                    'data' => array_map(fn ($v) => (float) $v, $trend['medication_amounts']),
                    'borderColor' => '#16a34a',
                    'backgroundColor' => 'rgba(22, 163, 74, 0.1)',
                    'tension' => 0.3,
                    'fill' => false,
                ],
                [
                    'label' => __('Services'),
                    'data' => array_map(fn ($v) => (float) $v, $trend['service_amounts']),
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'tension' => 0.3,
                    'fill' => false,
                ],
            ],
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
