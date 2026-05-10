<x-filament-panels::page>
    <div class="space-y-4">
        <x-filament::section>
            <x-slot name="heading">
                Pending medication items
            </x-slot>

            <p class="text-sm text-gray-600 dark:text-gray-300">
                Current in-progress request items awaiting dispensing:
                <span class="font-semibold">{{ $this->getPendingItemsCount() }}</span>
            </p>
        </x-filament::section>
    </div>
</x-filament-panels::page>

