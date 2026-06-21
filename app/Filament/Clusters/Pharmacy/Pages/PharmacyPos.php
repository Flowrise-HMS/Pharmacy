<?php

namespace Modules\Pharmacy\Filament\Clusters\Pharmacy\Pages;

use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Core\Classes\Services\BranchService;
use Modules\Core\Filament\Tables\Columns\CurrencyColumn;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Service;
use Modules\Core\Models\ServiceCategory;
use Modules\Patient\Models\Patient;
use Modules\Pharmacy\Classes\Services\PharmacyPosCheckoutService;
use Modules\Pharmacy\Classes\Support\PharmacyPosTotals;
use Modules\Pharmacy\Models\Medication;
use Modules\Pharmacy\Models\StockItem;

class PharmacyPos extends Page implements HasActions, HasTable
{
    use HasPageShield;
    use InteractsWithActions;
    use InteractsWithTable;

    protected static string $layout = 'filament-panels::components.layout.base';

    protected static ?int $navigationSort = -2;

    protected Width|string|null $maxContentWidth = 'full';

    protected static ?string $title = 'Point of Sale';

    protected static ?string $navigationLabel = 'Point of Sale';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shopping-cart';

    protected string $view = 'pharmacy::filament.clusters.pharmacy.pages.pharmacy-pos';

    public Collection $cart;

    public string $viewMode;

    public string $viewModeSessionKey;

    public $selectedBranchId = null;

    public $selectedPatientId = null;

    public ?string $cartCacheKey = null;

    public ?string $guestName = null;

    public ?string $guestPhone = null;

    public ?string $guestEmail = null;

    public ?string $patientSearch = '';

    public Collection $patientResults;

    public string $paymentMethod = 'cash';

    public float $discount = 0;

    public float $grandTotal = 0;

    public $amountPaid = null;

    public float $change = 0;

    public bool $autoPrintReceipt = false;

    public ?string $lastInvoiceNumber = null;

    public string $chargeMode = 'charge_account';

    public string $activeTab = 'medications';

    public function mount(): void
    {
        $this->cart = collect();
        $this->patientResults = collect();
        $this->paymentMethod = PaymentMethod::Cash->value;
        $this->viewModeSessionKey = Auth::id().'_pharmacy_pos_view_mode';
        $this->viewMode = Session::get($this->viewModeSessionKey, 'card');
        $this->activeTab = Session::get('pharmacy_pos_active_tab', 'medications');
        $this->selectedBranchId = $this->resolveDefaultBranchId();
        $this->cartCacheKey = 'pharmacy_pos_user_'.Auth::id().'_branch_'.($this->selectedBranchId ?? 'default');
        $this->restoreCartFromCache();
        $this->calculateGrandTotal();
    }

    protected function resolveDefaultBranchId(): ?string
    {
        if (app(BranchService::class)->getDefaultBranchId()) {
            return app(BranchService::class)->getDefaultBranchId();
        }

        return Branch::query()->where('is_default', true)->first()?->id;
    }

    public function toggleViewMode(string $mode): void
    {
        if ($mode !== $this->viewMode) {
            $this->viewMode = $mode;
            Session::put($this->viewModeSessionKey, $mode);
            $this->resetTable();
            Notification::make()
                ->title(__('View mode changed to')." <b>{$mode}</b>")
                ->success()
                ->send();
        }
    }

    public function updatedSelectedBranchId($value): void
    {
        $this->cartCacheKey = 'pharmacy_pos_user_'.Auth::id().'_branch_'.($value ?? 'default');
        $this->cart = collect();
        $this->calculateGrandTotal();
        $this->clearCartCache();
        $this->resetTable();
    }

    public function updatedActiveTab($value): void
    {
        Session::put('pharmacy_pos_active_tab', $value);
        $this->resetTable();
    }

    public function updatedChargeMode($value): void
    {
        $this->saveCartToCache();
    }

    public function updatedGuestName(): void
    {
        $this->saveCartToCache();
    }

    public function updatedGuestPhone(): void
    {
        $this->saveCartToCache();
    }

    public function updatedGuestEmail(): void
    {
        $this->saveCartToCache();
    }

    public function updatedPatientSearch($value): void
    {
        if (blank($value) || ! class_exists(Patient::class)) {
            $this->patientResults = collect();

            return;
        }

        $this->patientResults = Patient::query()
            ->where(function ($q) use ($value) {
                $q->where('first_name', 'like', "%{$value}%")
                    ->orWhere('last_name', 'like', "%{$value}%")
                    ->orWhere('mrn', 'like', "%{$value}%");
            })
            ->limit(10)
            ->get()
            ->map(fn ($p) => ['id' => $p->id, 'label' => $p->full_name.' ('.$p->mrn.')']);
    }

    public function selectPatient($id): void
    {
        $this->selectedPatientId = $id ?: null;
        $this->patientSearch = '';
        $this->patientResults = collect();
        $this->saveCartToCache();
    }

    public function table(Table $table): Table
    {
        if ($this->activeTab === 'services') {
            return $this->makeServicesTable($table);
        }

        return $this->makeMedicationsTable($table);
    }

    protected function makeMedicationsTable(Table $table): Table
    {
        return $table
            ->query($this->medicationsTableQuery())
            ->defaultSort('generic_name')
            ->paginationPageOptions([12, 24, 48])
            ->searchPlaceholder(__('Search medications…'))
            ->searchable()
            ->searchUsing(function (Builder $query, string $search): void {
                $query->where(function (Builder $q) use ($search): void {
                    $q->where('generic_name', 'like', "%{$search}%")
                        ->orWhere('brand_name', 'like', "%{$search}%")
                        ->orWhere('strength', 'like', "%{$search}%")
                        ->orWhereHas('service', fn (Builder $sq) => $sq->where('name', 'like', "%{$search}%"));
                });
            })
            ->columns($this->viewMode === 'card' ? $this->getMedicationCardColumns() : $this->getMedicationTableColumns())
            ->contentGrid($this->viewMode === 'card' ? ['md' => 2, 'xl' => 3] : null)
            ->emptyStateHeading(fn (): string => blank($this->selectedBranchId)
                ? __('Select a branch')
                : __('No medications in stock'))
            ->emptyStateDescription(fn (): string => blank($this->selectedBranchId)
                ? __('Choose an active branch to browse medications.')
                : __('No active medications with quantity for this branch.'))
            ->recordAction('add_to_cart')
            ->recordActions([
                Action::make('add_to_cart')
                    ->action(fn (Medication $record) => $this->addToCart($record->id)),
            ]);
    }

    protected function makeServicesTable(Table $table): Table
    {
        return $table
            ->query(Service::query()
                ->where('is_active', true)
                ->where('is_billable', true)
                ->whereHas('category', fn ($q) => $q->where('code', '!=', 'MED'))
                ->with('category'))
            ->defaultSort('name')
            ->paginationPageOptions([12, 24, 48])
            ->searchPlaceholder(__('Search services…'))
            ->searchable()
            ->searchUsing(function (Builder $query, string $search): void {
                $query->where(function (Builder $q) use ($search): void {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhereHas('category', fn (Builder $cq) => $cq->where('name', 'like', "%{$search}%"));
                });
            })
            ->columns($this->viewMode === 'card' ? $this->getServiceCardColumns() : $this->getServiceTableColumns())
            ->contentGrid($this->viewMode === 'card' ? ['md' => 2, 'xl' => 3] : null)
            ->emptyStateHeading(__('No services available'))
            ->emptyStateDescription(__('No active billable services found.'))
            ->recordAction('add_service_to_cart')
            ->actions([
                Action::make('add_service_to_cart')
                    ->action(fn (Service $record) => $this->addServiceToCart($record->id)),
            ]);
    }

    protected function medicationsTableQuery(): Builder
    {
        $query = Medication::query()->where('is_active', true);

        if (blank($this->selectedBranchId)) {
            return $query->whereRaw('0 = 1');
        }

        return $query
            ->whereHas('stockItems', fn ($q) => $q
                ->where('branch_id', $this->selectedBranchId)
                ->where('quantity_on_hand', '>', 0))
            ->with([
                'service',
                'stockUnit',
                'billingUnit',
                'stockItems' => fn ($q) => $q->where('branch_id', $this->selectedBranchId),
            ]);
    }

    /**
     * @return array<int, Stack|TextColumn>
     */
    protected function getMedicationCardColumns(): array
    {
        return [
            Stack::make([
                TextColumn::make('price_display')
                    ->label(__('Product'))
                    ->state(fn (?Medication $record): string => $record?->billingService()?->name ?? $record?->displayName())
                    ->size('lg')
                    ->weight('bold')
                    ->alignStart()
                    ->extraAttributes(['class' => 'group-hover:text-primary-600 dark:group-hover:text-primary-400 transition']),
                TextColumn::make('generic_name')
                    ->label(__('Generic'))
                    ->alignStart()
                    ->color('gray')
                    ->size('sm')
                    ->visible(fn (?Medication $record): bool => filled($record?->generic_name) && ($record?->billingService()?->name !== $record?->generic_name)),
                TextColumn::make('strength')
                    ->alignStart()
                    ->color('gray')
                    ->size('xs')
                    ->placeholder('—'),
                TextColumn::make('stock_display')
                    ->state(function (?Medication $record): string {
                        $service = $record?->billingService();
                        $price = $service ? number_format((float) $service->price, 2) : '0.00';
                        $currency = config('core.default_currency');
                        $qty = (int) ($record?->stockItems?->sum('quantity_on_hand') ?? 0);
                        $unit = $record?->stockUnit?->label ?? '';

                        $priceLabel = match (true) {
                            ! $service => __('No billing'),
                            (float) $service->price <= 0 => __('Free'),
                            default => "{$currency} {$price}",
                        };
                        $stockLabel = $qty > 0 ? "{$qty} {$unit} ".__('in stock') : __('Out of stock');

                        return "{$priceLabel} | {$stockLabel}";
                    })
                    ->color(fn (?Medication $record): string => $record?->billingService() ? 'gray' : 'danger')
                    ->size('sm'),
            ])->extraAttributes(['class' => 'p-4 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm hover:shadow-md transition cursor-pointer group']),
        ];
    }

    /**
     * @return array<int, Stack|TextColumn>
     */
    protected function getServiceCardColumns(): array
    {
        return [
            Stack::make([
                TextColumn::make('name')
                    ->label(__('Service'))
                    ->size('lg')
                    ->weight('bold')
                    ->alignStart()
                    ->extraAttributes(['class' => 'group-hover:text-primary-600 dark:group-hover:text-primary-400 transition']),
                TextColumn::make('category.name')
                    ->label(__('Category'))
                    ->alignStart()
                    ->color('gray')
                    ->size('sm'),
                TextColumn::make('price_display')
                    ->state(fn (Service $record): string => number_format((float) $record->price, 2))
                    ->color('gray')
                    ->size('sm'),
            ])->extraAttributes(['class' => 'p-4 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm hover:shadow-md transition cursor-pointer group']),
        ];
    }

    /**
     * @return array<int, TextColumn>
     */
    protected function getServiceTableColumns(): array
    {
        return [
            TextColumn::make('name')
                ->label(__('Name'))
                ->weight('bold')
                ->searchable(),
            TextColumn::make('category.name')
                ->label(__('Category'))
                ->badge()
                ->color('gray'),
            CurrencyColumn::make('price')
                ->label(__('Price'))
                ->alignRight()
                ->weight('bold'),
        ];
    }

    /**
     * @return array<int, TextColumn>
     */
    protected function getMedicationTableColumns(): array
    {
        return [
            TextColumn::make('name_display')
                ->label(__('Name'))
                ->state(fn (?Medication $record): string => $record?->billingService()?->name ?? $record?->displayName())
                ->weight('bold')
                ->description(fn (?Medication $record): ?string => $record?->billingService()?->name && $record?->displayName() !== $record?->billingService()?->name
                    ? $record?->displayName()
                    : null),
            TextColumn::make('strength')
                ->label(__('Strength'))
                ->placeholder('—'),
            CurrencyColumn::make('price')
                ->label(__('Price'))
                ->state(fn (?Medication $record): string => $record?->billingService()?->price ?? '0')
                ->alignRight()
                ->weight('bold'),
            TextColumn::make('stock_qty')
                ->label(__('Stock'))
                ->state(function (?Medication $record): string {
                    $qty = (int) ($record?->stockItems?->sum('quantity_on_hand') ?? 0);
                    $unit = $record?->stockUnit?->label ?? '';

                    return $unit ? "{$qty} {$unit}" : (string) $qty;
                })
                ->badge()
                ->alignCenter()
                ->color(function ($state): string {
                    $parts = explode(' ', (string) $state);
                    $qty = (int) ($parts[0] ?? 0);
                    return match (true) {
                        $qty > 10 => 'success',
                        $qty > 0 => 'warning',
                        default => 'danger',
                    };
                }),
        ];
    }

    public function addToCart($medicationId): void
    {
        if (blank($this->selectedBranchId)) {
            Notification::make()
                ->danger()
                ->title(__('Branch required'))
                ->body(__('Select a branch before adding items to the cart.'))
                ->send();

            return;
        }

        $medication = Medication::query()
            ->with(['service', 'billingUnit', 'stockItems' => fn ($q) => $q->where('branch_id', $this->selectedBranchId)])
            ->findOrFail($medicationId);

        $stockQty = $medication->stockItems->sum('quantity_on_hand');

        if ($stockQty <= 0) {
            Notification::make()
                ->danger()
                ->title(__('Out of Stock'))
                ->body(($medication->service?->name ?? $medication->generic_name).' '.__('is out of stock.'))
                ->send();

            return;
        }

        $key = 'm'.$medication->id;

        if ($this->cart->has($key)) {
            $item = $this->cart->get($key);
            if ($item['quantity'] >= $stockQty) {
                Notification::make()
                    ->danger()
                    ->title(__('Stock limit reached'))
                    ->send();

                return;
            }
            $item['quantity']++;
            $this->cart->put($key, $item);
        } else {
            $this->cart[$key] = [
                'type' => 'medication',
                'id' => $medication->id,
                'name' => $medication->service?->name ?? $medication->generic_name,
                'generic_name' => $medication->generic_name,
                'strength' => $medication->strength,
                'price' => (float) ($medication->service?->price ?? 0),
                'quantity' => 1,
                'available' => $stockQty,
                'unit_label' => $medication->billingUnit?->label ?? '',
            ];
        }

        $this->calculateGrandTotal();
        $this->saveCartToCache();
    }

    public function addServiceToCart($serviceId): void
    {
        $service = Service::query()
            ->where('is_active', true)
            ->where('is_billable', true)
            ->with(['category', 'billingUnit'])
            ->findOrFail($serviceId);

        $key = 's'.$service->id;

        if ($this->cart->has($key)) {
            $item = $this->cart->get($key);
            $item['quantity']++;
            $this->cart->put($key, $item);
        } else {
            $this->cart[$key] = [
                'type' => 'service',
                'id' => $service->id,
                'name' => $service->name,
                'category' => $service->category?->name,
                'price' => (float) ($service->price ?? 0),
                'quantity' => 1,
                'unit_label' => $service->billingUnit?->label ?? '',
            ];
        }

        $this->calculateGrandTotal();
        $this->saveCartToCache();
    }

    public function updateQuantity($id, $quantity): void
    {
        if (! $this->cart->has($id)) {
            return;
        }

        $quantity = max(1, (int) $quantity);
        $item = $this->cart->get($id);

        if (($item['type'] ?? 'medication') === 'medication') {
            $stockQty = StockItem::query()
                ->where('medication_id', $item['id'])
                ->where('branch_id', $this->selectedBranchId)
                ->sum('quantity_on_hand');

            if ($quantity > $stockQty) {
                Notification::make()
                    ->danger()
                    ->title(__('Insufficient stock'))
                    ->send();

                return;
            }
        }

        $item['quantity'] = $quantity;
        $this->cart->put($id, $item);
        $this->calculateGrandTotal();
        $this->saveCartToCache();
    }

    public function removeFromCart($id): void
    {
        $this->cart->forget($id);
        $this->calculateGrandTotal();
        $this->saveCartToCache();
    }

    public function calculateGrandTotal(): void
    {
        $subtotal = PharmacyPosTotals::cartSubtotal($this->cart);
        $discountStr = PharmacyPosTotals::normalizeMoney($this->discount);
        $grand = PharmacyPosTotals::grandTotalAfterDiscount($subtotal, $discountStr);
        $this->grandTotal = (float) $grand;
    }

    public function calculateChange(): void
    {
        $this->change = max(0, (float) ($this->amountPaid ?? 0) - $this->grandTotal);
    }

    public function updatedDiscount($value): void
    {
        $this->discount = (float) ($value ?? 0);
        $this->calculateGrandTotal();
        $this->calculateChange();
        $this->saveCartToCache();
    }

    public function updatedAmountPaid($value): void
    {
        $this->amountPaid = $value !== '' && $value !== null ? (float) $value : null;
        $this->calculateChange();
        $this->saveCartToCache();
    }

    public function processPayment(string $method): void
    {
        $this->paymentMethod = $method;
        $this->checkout();
    }

    public function checkout(): void
    {
        if ($this->cart->isEmpty()) {
            Notification::make()
                ->danger()
                ->title(__('Cart is empty'))
                ->send();

            return;
        }

        if (blank($this->selectedBranchId)) {
            Notification::make()
                ->danger()
                ->title(__('Branch required'))
                ->body(__('Select a branch before checkout.'))
                ->send();

            return;
        }

        if (! $this->selectedPatientId && blank($this->guestName) && blank($this->guestPhone)) {
            Notification::make()
                ->danger()
                ->title(__('Owner required'))
                ->body(__('Select a patient or enter the guest name / phone.'))
                ->send();

            return;
        }

        $subtotal = PharmacyPosTotals::cartSubtotal($this->cart);
        $discountStr = PharmacyPosTotals::normalizeMoney($this->discount);
        if (bccomp($discountStr, $subtotal, 2) > 0) {
            Notification::make()
                ->danger()
                ->title(__('Invalid discount'))
                ->body(__('Discount cannot exceed the cart subtotal.'))
                ->send();

            return;
        }

        if ($this->chargeMode === 'charge_account') {
            $this->checkoutChargeToAccount();

            return;
        }

        try {
            $amountTendered = $this->amountPaid !== null && $this->amountPaid !== ''
                ? PharmacyPosTotals::normalizeMoney($this->amountPaid)
                : null;

            $result = app(PharmacyPosCheckoutService::class)->checkout([
                'branch_id' => $this->selectedBranchId,
                'patient_id' => $this->selectedPatientId,
                'guest_name' => $this->selectedPatientId ? null : $this->guestName,
                'guest_phone' => $this->selectedPatientId ? null : $this->guestPhone,
                'guest_email' => $this->selectedPatientId ? null : $this->guestEmail,
                'currency' => config('core.default_currency'),
                'cart' => $this->cart->map(fn ($item) => [
                    'type' => $item['type'] ?? 'medication',
                    'id' => $item['id'],
                    'quantity' => $item['quantity'],
                ])->values()->toArray(),
                'payment_method' => PaymentMethod::from($this->paymentMethod),
                'pos_discount_amount' => $discountStr,
                'amount_tendered' => $amountTendered,
            ]);

            $this->lastInvoiceNumber = $result['invoice']->invoice_number;

            Notification::make()
                ->title(__('Checkout successful'))
                ->body(__('Invoice:').' '.$result['invoice']->invoice_number)
                ->success()
                ->duration(10000)
                ->send();

            if ($this->autoPrintReceipt) {
                $receiptUrl = isset($result['payment'])
                    ? $this->buildReceiptUrl($result['payment']->id)
                    : $this->buildInvoiceUrl($result['invoice']->id);
                if ($receiptUrl) {
                    $this->dispatch('pos-open-receipt', url: $receiptUrl);
                }
            }

            $this->resetState();
        } catch (\Throwable $e) {
            Notification::make()
                ->title(__('Checkout failed'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function checkoutChargeToAccount(): void
    {
        try {
            $discountStr = PharmacyPosTotals::normalizeMoney($this->discount);

            $result = app(PharmacyPosCheckoutService::class)->checkoutChargeToAccount([
                'branch_id' => $this->selectedBranchId,
                'patient_id' => $this->selectedPatientId,
                'guest_name' => $this->selectedPatientId ? null : $this->guestName,
                'guest_phone' => $this->selectedPatientId ? null : $this->guestPhone,
                'guest_email' => $this->selectedPatientId ? null : $this->guestEmail,
                'currency' => config('core.default_currency'),
                'cart' => $this->cart->map(fn ($item) => [
                    'type' => $item['type'] ?? 'medication',
                    'id' => $item['id'],
                    'quantity' => $item['quantity'],
                ])->values()->toArray(),
                'pos_discount_amount' => $discountStr,
            ]);

            $invoice = $result['invoice'];

            $billingDeskUrl = \Modules\Billing\Filament\Clusters\Billing\Pages\BillingDesk::getUrl(['invoice' => $invoice->id]);

            $this->lastInvoiceNumber = $invoice->invoice_number;

            Notification::make()
                ->title(__('Charge created'))
                ->body(__('Invoice :number sent to billing desk.', ['number' => $invoice->invoice_number]))
                ->success()
                ->duration(10000)
                ->actions([
                    Action::make('open_billing')
                        ->label(__('Open in Billing Desk'))
                        ->button()
                        ->url($billingDeskUrl),
                ])
                ->send();

            if ($this->autoPrintReceipt) {
                $invoiceUrl = $this->buildInvoiceUrl($invoice->id);
                if ($invoiceUrl) {
                    $this->dispatch('pos-open-receipt', url: $invoiceUrl);
                }
            }

            $this->resetState();
        } catch (\Throwable $e) {
            Notification::make()
                ->title(__('Charge failed'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function resetState(): void
    {
        $this->cart = collect();
        $this->selectedPatientId = null;
        $this->guestName = null;
        $this->guestPhone = null;
        $this->guestEmail = null;
        $this->patientSearch = '';
        $this->patientResults = collect();
        $this->discount = 0;
        $this->grandTotal = 0;
        $this->amountPaid = null;
        $this->change = 0;
        $this->paymentMethod = PaymentMethod::Cash->value;
        $this->clearCartCache();
    }

    public function clearCart(): void
    {
        $this->resetState();
        $this->lastInvoiceNumber = null;
    }

    protected function saveCartToCache(): void
    {
        if (! $this->cartCacheKey) {
            return;
        }

        Cache::put($this->cartCacheKey, [
            'items' => $this->cart,
            'guest_name' => $this->guestName,
            'guest_phone' => $this->guestPhone,
            'guest_email' => $this->guestEmail,
            'discount' => $this->discount,
            'amount_paid' => $this->amountPaid,
            'payment_method' => $this->paymentMethod,
            'selected_patient_id' => $this->selectedPatientId,
            'auto_print_receipt' => $this->autoPrintReceipt,
        ], now()->addHours(1));
    }

    protected function restoreCartFromCache(): void
    {
        if (! $this->cartCacheKey) {
            return;
        }

        $cached = Cache::get($this->cartCacheKey);

        if (is_array($cached) && ! empty($cached)) {
            $this->cart = $cached['items'] ?? collect();
            $this->guestName = $cached['guest_name'] ?? null;
            $this->guestPhone = $cached['guest_phone'] ?? null;
            $this->guestEmail = $cached['guest_email'] ?? null;
            $this->discount = $cached['discount'] ?? 0;
            $this->amountPaid = $cached['amount_paid'] ?? null;
            $this->paymentMethod = $cached['payment_method'] ?? PaymentMethod::Cash->value;
            $this->selectedPatientId = $cached['selected_patient_id'] ?? null;
            $this->autoPrintReceipt = $cached['auto_print_receipt'] ?? false;
        }

        if (! $this->cart instanceof Collection) {
            $this->cart = collect();
        }
    }

    protected function clearCartCache(): void
    {
        if ($this->cartCacheKey) {
            Cache::forget($this->cartCacheKey);
        }
    }

    protected function buildReceiptUrl($paymentId): ?string
    {
        if (! $paymentId) {
            return null;
        }

        try {
            return route('billing.payments.receipt', ['payment' => $paymentId]);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function buildInvoiceUrl($invoiceId): ?string
    {
        if (! $invoiceId) {
            return null;
        }

        try {
            return route('billing.invoices.pdf', ['invoice' => $invoiceId]);
        } catch (\Throwable) {
            return null;
        }
    }

    public function canCreatePayment(): bool
    {
        return auth()->user()?->can('create', \Modules\Billing\Models\Payment::class) ?? false;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('home')
                ->icon('heroicon-o-home')
                ->url(filament()->getCurrentPanel()?->getUrl()),
            Action::make('clearCart')
                ->label(__('Clear cart'))
                ->color('gray')
                ->action('clearCart'),
        ];
    }

    protected function getActions(): array
    {
        return [];
    }

    public function buildPrescriptionSlipUrl(string $requestItemId): ?string
    {
        try {
            return route('pharmacy.prescription-slip', ['requestItem' => $requestItemId]);
        } catch (\Throwable) {
            return null;
        }
    }
}
