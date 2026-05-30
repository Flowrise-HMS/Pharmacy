<x-filament-panels::page>
    <style>
        .pos-cart-container { width: 100%; }
        @media (min-width: 1024px) {
            .pos-cart-container { width: 380px; }
            .pos-cart-sticky { position: sticky; top: 1rem; }
        }
    </style>
    <div class="flex flex-col lg:flex-row gap-6 min-h-[85vh]">
        {{-- Catalog + toolbar --}}
        <div class="flex-1 space-y-4 min-w-0">
            <x-filament::tabs>
                <x-filament::tabs.item
                    :active="$activeTab === 'medications'"
                    wire:click="$set('activeTab', 'medications')"
                    icon="heroicon-m-cube"
                >
                    {{ __('Medications') }}
                </x-filament::tabs.item>
                <x-filament::tabs.item
                    :active="$activeTab === 'services'"
                    wire:click="$set('activeTab', 'services')"
                    icon="heroicon-m-clipboard-document-list"
                >
                    {{ __('Services') }}
                </x-filament::tabs.item>
            </x-filament::tabs>

            <div class="p-4 bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">{{ __('Branch') }}</label>
                        <select wire:model.live="selectedBranchId"
                            class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm py-2 px-3 focus:ring-primary-500 focus:border-primary-500">
                            <option value="">{{ __('Select branch') }}</option>
                            @foreach(\Modules\Core\Models\Branch::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id') as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="relative">
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">{{ __('Patient') }}</label>
                        <x-filament::input.wrapper>
                            <x-filament::input
                                type="text"
                                placeholder="{{ __('Search patient…') }}"
                                wire:model.live.debounce.300ms="patientSearch"
                                prefix-icon="heroicon-m-user"
                            />
                        </x-filament::input.wrapper>

                        @if($patientResults->isNotEmpty())
                            <div class="absolute z-50 mt-1 w-full bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                @foreach($patientResults as $result)
                                    <button type="button" wire:click="selectPatient('{{ $result['id'] }}')"
                                        class="w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600 transition">
                                        {{ $result['label'] }}
                                    </button>
                                @endforeach
                            </div>
                        @endif

                        @if($selectedPatientId)
                            @php
                                $patientModel = class_exists(\Modules\Patient\Models\Patient::class)
                                    ? \Modules\Patient\Models\Patient::find($selectedPatientId)
                                    : null;
                            @endphp
                            @if($patientModel)
                                <div class="mt-1 flex items-center gap-2 text-xs text-primary-600 dark:text-primary-400">
                                    <span>{{ $patientModel->full_name }} ({{ $patientModel->mrn }})</span>
                                    <button type="button" wire:click="selectPatient(null)" class="text-red-500 hover:text-red-700">&times;</button>
                                </div>
                            @endif
                        @endif
                    </div>
                </div>

                @if(!$selectedPatientId)
                    <div class="mt-3 grid grid-cols-1 sm:grid-cols-3 gap-2">
                        <x-filament::input
                            type="text"
                            placeholder="{{ __('Guest name') }}"
                            wire:model.live.debounce.300ms="guestName"
                            class="text-sm"
                        />
                        <x-filament::input
                            type="text"
                            placeholder="{{ __('Guest phone') }}"
                            wire:model.live.debounce.300ms="guestPhone"
                            class="text-sm"
                        />
                        <x-filament::input
                            type="email"
                            placeholder="{{ __('Guest email') }}"
                            wire:model.live.debounce.300ms="guestEmail"
                            class="text-sm"
                        />
                    </div>
                @endif
            </div>

            <x-filament::tabs>
                <x-filament::tabs.item icon="heroicon-m-squares-2x2"
                    :active="$viewMode === 'card'"
                    wire:click="toggleViewMode('card')">
                    {{ __('Cards') }}
                </x-filament::tabs.item>

                <x-filament::tabs.item icon="heroicon-m-table-cells"
                    :active="$viewMode === 'table'"
                    wire:click="toggleViewMode('table')">
                    {{ __('Table') }}
                </x-filament::tabs.item>
            </x-filament::tabs>

            <div class="overflow-x-auto">
                {{ $this->table }}
            </div>
        </div>

        {{-- Cart --}}
        <div class="pos-cart-container shrink-0">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4 pos-cart-sticky space-y-4 border border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between pb-3 border-b border-gray-200 dark:border-gray-700">
                    <span class="text-sm text-gray-500 dark:text-gray-400">
                        {{ $cart->count() }} {{ trans_choice('item|items', $cart->count()) }}
                    </span>
                </div>

                <div class="space-y-3 max-h-96 overflow-y-auto pr-1">
                    @forelse($cart as $id => $item)
                        <div class="flex items-start gap-3 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                            <div class="flex-1 min-w-0">
                                <p class="font-medium text-sm text-gray-900 dark:text-white truncate">
                                    {{ $item['name'] }}
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                    {{ config('core.default_currency') }} {{ number_format($item['price'], 2) }}
                                </p>
                                <div class="flex items-center gap-2 mt-1.5">
                                    <button type="button" wire:click="updateQuantity('{{ $id }}', {{ $item['quantity'] - 1 }})"
                                        class="w-6 h-6 flex items-center justify-center rounded bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-300 dark:hover:bg-gray-500 text-sm font-medium transition"
                                        @if($item['quantity'] <= 1) disabled @endif>
                                        &minus;
                                    </button>
                                    <input type="number"
                                        wire:change="updateQuantity('{{ $id }}', $event.target.value)"
                                        value="{{ $item['quantity'] }}" min="1"
                                        class="w-12 text-center text-sm rounded border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white py-0.5" />
                                    <button type="button" wire:click="updateQuantity('{{ $id }}', {{ $item['quantity'] + 1 }})"
                                        class="w-6 h-6 flex items-center justify-center rounded bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-300 dark:hover:bg-gray-500 text-sm font-medium transition">
                                        +
                                    </button>
                                </div>
                            </div>
                            <div class="text-right flex-shrink-0">
                                <p class="font-semibold text-sm text-gray-900 dark:text-white">
                                    {{ config('core.default_currency') }} {{ number_format($item['price'] * $item['quantity'], 2) }}
                                </p>
                                <button type="button" wire:click="removeFromCart('{{ $id }}')"
                                    class="text-xs text-red-500 hover:text-red-700 mt-1 transition">
                                    {{ __('Remove') }}
                                </button>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-10 text-gray-400 dark:text-gray-500 text-center">
                            <x-heroicon-o-shopping-cart class="w-4 h-4 mx-auto mb-2 opacity-50 text-center" />
                            <p class="text-sm">{{ __('Your cart is empty') }}</p>
                            <p class="text-xs mt-1">{{ __('Click a medication or service to add it to the cart') }}</p>
                        </div>
                    @endforelse
                </div>

                <div class="pt-3 border-t border-gray-200 dark:border-gray-700 space-y-3">
                    <div class="flex items-center justify-between gap-2">
                        <label class="text-sm text-gray-600 dark:text-gray-400">{{ __('Discount') }}</label>
                        <x-filament::input
                            type="number"
                            step="0.01"
                            min="0"
                            wire:model.live.debounce.500ms="discount"
                            placeholder="0.00"
                            class="w-28 text-right text-sm"
                        />
                    </div>

                    <div class="flex items-center justify-between pt-2 border-t border-gray-100 dark:border-gray-700">
                        <span class="text-base font-bold text-gray-900 dark:text-white">{{ __('Grand total') }}</span>
                        <span class="text-xl font-extrabold text-primary-600 dark:text-primary-400">
                            {{ config('core.default_currency') }} {{ number_format($grandTotal, 2) }}
                        </span>
                    </div>

                    <div class="flex items-center gap-4 pt-1">
                        @if($this->canCreatePayment())
                            <label class="flex items-center gap-1.5 text-sm cursor-pointer">
                                <input type="radio" wire:model.live="chargeMode" value="pay_now"
                                    class="text-primary-600 focus:ring-primary-500"
                                    @disabled($cart->isEmpty()) />
                                <span class="text-gray-700 dark:text-gray-300">{{ __('Pay now') }}</span>
                            </label>
                        @endif
                        <label class="flex items-center gap-1.5 text-sm cursor-pointer">
                            <input type="radio" wire:model.live="chargeMode" value="charge_account"
                                class="text-primary-600 focus:ring-primary-500"
                                @disabled($cart->isEmpty()) />
                            <span class="text-gray-700 dark:text-gray-300">{{ __('Post to account') }}</span>
                        </label>
                    </div>

                    @if ($chargeMode === 'pay_now')
                        <div class="flex items-center justify-between gap-2">
                            <label class="text-sm text-gray-600 dark:text-gray-400">{{ __('Amount paid') }}</label>
                            <x-filament::input
                                type="number"
                                step="0.01"
                                min="0"
                                wire:model.live.debounce.500ms="amountPaid"
                                placeholder="0.00"
                                class="w-28 text-right text-sm"
                            />
                        </div>

                        <div class="flex items-center justify-between text-sm">
                            <span class="text-gray-600 dark:text-gray-400">{{ __('Change') }}</span>
                            <span class="font-semibold text-gray-900 dark:text-white">
                                {{ config('core.default_currency') }} {{ number_format($change, 2) }}
                            </span>
                        </div>
                    @endif

                    <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400 cursor-pointer pt-2">
                        <input type="checkbox" wire:model.live="autoPrintReceipt" class="rounded border-gray-300 dark:border-gray-600 text-primary-600 shadow-sm focus:ring-primary-500" />
                        {{ __('Auto print receipt after payment') }}
                    </label>

                    @if ($chargeMode === 'pay_now')
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">{{ __('Payment method') }}</label>
                            <select wire:model.live="paymentMethod"
                                class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm py-2 px-3 focus:ring-primary-500 focus:border-primary-500"
                                @disabled($cart->isEmpty())>
                                <option value="cash">{{ __('Cash') }}</option>
                                <option value="card">{{ __('Card') }}</option>
                                <option value="bank_transfer">{{ __('Bank Transfer') }}</option>
                                <option value="mobile_money">{{ __('Mobile Money') }}</option>
                            </select>
                        </div>
                    @endif

                    <button type="button" wire:click="checkout"
                        class="w-full px-4 py-3 text-sm font-bold dark:text-white rounded-lg transition shadow-sm flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed
                            {{ $chargeMode === 'pay_now' ? 'bg-green-600 hover:bg-green-700' : 'bg-blue-600 hover:bg-blue-700' }}"
                        @disabled($cart->isEmpty())>
                        @if ($chargeMode === 'pay_now')
                            <x-heroicon-m-currency-dollar class="w-5 h-5" />
                            {{ __('Dispense & Checkout') }}
                        @else
                            <x-heroicon-m-arrow-right-on-rectangle class="w-5 h-5" />
                            {{ __('Send to Billing') }}
                        @endif
                    </button>
                </div>
            </div>
        </div>
    </div>
<script>
    window.addEventListener('pos-open-receipt', function (event) {
        var url = event.detail && event.detail.url;
        if (url) {
            window.open(url, '_blank', 'noopener');
        }
    });
</script>
</x-filament-panels::page>
