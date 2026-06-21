<x-filament-panels::page>
    <div class="flex flex-wrap gap-2 mb-4">
        <x-filament::button
            tag="a"
            :href="$this->presetUrl('today')"
            :color="$this->preset === 'today' ? 'primary' : 'gray'"
            size="sm"
        >
            {{ __('Today') }}
        </x-filament::button>
        <x-filament::button
            tag="a"
            :href="$this->presetUrl('week')"
            :color="$this->preset === 'week' ? 'primary' : 'gray'"
            size="sm"
        >
            {{ __('This week') }}
        </x-filament::button>
        <x-filament::button
            tag="a"
            :href="$this->presetUrl('month')"
            :color="$this->preset === 'month' ? 'primary' : 'gray'"
            size="sm"
        >
            {{ __('This month') }}
        </x-filament::button>
    </div>

    <form method="GET" class="grid gap-4 md:grid-cols-6">
        <input type="hidden" name="preset" value="custom">
        <div>
            <label class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('Start date') }}</label>
            <input
                type="date"
                name="start_date"
                value="{{ $this->startDate }}"
                class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900"
            >
        </div>
        <div>
            <label class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('End date') }}</label>
            <input
                type="date"
                name="end_date"
                value="{{ $this->endDate }}"
                class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900"
            >
        </div>
        <div>
            <label class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('Branch') }}</label>
            <select
                name="branch_id"
                class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900"
            >
                <option value="">{{ __('All branches') }}</option>
                @foreach ($this->branchOptions as $id => $name)
                    <option value="{{ $id }}" @selected($this->branchId === (string) $id)>{{ $name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('Line kind') }}</label>
            <select
                name="line_kind"
                class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900"
            >
                @foreach ($this->lineKindOptions as $value => $label)
                    <option value="{{ $value }}" @selected($this->lineKind === (string) $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('Channel') }}</label>
            <select
                name="channel"
                class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900"
            >
                @foreach ($this->channelOptions as $value => $label)
                    <option value="{{ $value }}" @selected($this->channel === (string) $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-end">
            <x-filament::button type="submit">{{ __('Apply') }}</x-filament::button>
        </div>
    </form>

    <div class="grid gap-4 md:grid-cols-3 xl:grid-cols-6 mt-6">
        <x-filament::section>
            <div class="text-sm text-gray-500">{{ __('Total sales') }}</div>
            <div class="text-2xl font-semibold">{{ number_format((float) data_get($this->report, 'summary.total_sales', 0), 2) }}</div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-sm text-gray-500">{{ __('Medication sales') }}</div>
            <div class="text-2xl font-semibold text-success-600">{{ number_format((float) data_get($this->report, 'summary.medication_sales', 0), 2) }}</div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-sm text-gray-500">{{ __('Service sales') }}</div>
            <div class="text-2xl font-semibold text-primary-600">{{ number_format((float) data_get($this->report, 'summary.service_sales', 0), 2) }}</div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-sm text-gray-500">{{ __('Dispenses') }}</div>
            <div class="text-2xl font-semibold">{{ data_get($this->report, 'summary.dispenses', 0) }}</div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-sm text-gray-500">{{ __('Avg ticket') }}</div>
            <div class="text-2xl font-semibold">
                @if (data_get($this->report, 'summary.avg_ticket') !== null)
                    {{ number_format((float) data_get($this->report, 'summary.avg_ticket'), 2) }}
                @else
                    —
                @endif
            </div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-sm text-gray-500">{{ __('Low stock') }}</div>
            <div class="text-2xl font-semibold text-danger-600">{{ data_get($this->report, 'summary.low_stock_count', 0) }}</div>
        </x-filament::section>
    </div>

    @if ($this->getFooterWidgets())
        <div class="grid gap-4 md:grid-cols-2 mt-6">
            <x-filament-widgets::widgets
                :widgets="$this->getFooterWidgets()"
                :columns="['md' => 2, 'xl' => 2]"
            />
        </div>
    @endif

    @if ($this->getReportTableWidgets())
        <div class="grid gap-4 mt-6">
            <x-filament-widgets::widgets
                :widgets="$this->getReportTableWidgets()"
                :columns="1"
            />
        </div>
    @endif
</x-filament-panels::page>
