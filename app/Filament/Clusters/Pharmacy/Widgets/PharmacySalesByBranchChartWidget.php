<?php

namespace Modules\Pharmacy\Filament\Clusters\Pharmacy\Widgets;

use Filament\Widgets\ChartWidget;
use Livewire\Attributes\Reactive;
use Modules\Core\Filament\Concerns\InteractsWithWidgetShield;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\PharmacyCluster;

class PharmacySalesByBranchChartWidget extends ChartWidget
{
    use InteractsWithWidgetShield;

    protected static ?string $cluster = PharmacyCluster::class;

    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Sales by branch';

    protected int|string|array $columnSpan = 1;

    #[Reactive]
    public ?array $reportPayload = null;

    protected function getData(): array
    {
        $branches = $this->reportPayload['sales_by_branch'] ?? [];

        if ($branches === []) {
            return ['labels' => [], 'datasets' => []];
        }

        return [
            'labels' => array_column($branches, 'branch_name'),
            'datasets' => [
                [
                    'label' => __('Medications'),
                    'data' => array_map(fn (array $row) => (float) ($row['medication_amount'] ?? 0), $branches),
                    'backgroundColor' => '#16a34a',
                ],
                [
                    'label' => __('Services'),
                    'data' => array_map(fn (array $row) => (float) ($row['service_amount'] ?? 0), $branches),
                    'backgroundColor' => '#3b82f6',
                ],
            ],
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
