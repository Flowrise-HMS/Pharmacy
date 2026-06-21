<?php

namespace Modules\Pharmacy\Filament\Clusters\Pharmacy\Widgets;

use Filament\Widgets\ChartWidget;
use Livewire\Attributes\Reactive;
use Modules\Core\Filament\Concerns\InteractsWithWidgetShield;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\PharmacyCluster;

class PrescriptionStatusDonutChartWidget extends ChartWidget
{
    use InteractsWithWidgetShield;

    protected static ?string $cluster = PharmacyCluster::class;

    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Prescription status';

    protected int|string|array $columnSpan = 1;

    #[Reactive]
    public ?array $reportPayload = null;

    protected function getData(): array
    {
        $split = $this->reportPayload['prescription_status'] ?? ['labels' => [], 'counts' => []];

        if ($split['labels'] === []) {
            return ['labels' => [], 'datasets' => []];
        }

        return [
            'labels' => $split['labels'],
            'datasets' => [[
                'data' => array_map(fn ($v) => (int) $v, $split['counts']),
                'backgroundColor' => ['#f59e0b', '#3b82f6', '#16a34a', '#6b7280'],
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
