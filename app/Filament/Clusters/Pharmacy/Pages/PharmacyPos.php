<?php

namespace Modules\Pharmacy\Filament\Clusters\Pharmacy\Pages;

use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Core\Classes\Services\BranchService;
use Modules\Core\Models\Branch;
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

    public function mount(): void
    {
        $this->cart = collect();
        $this->patientResults = collect();
        $this->paymentMethod = PaymentMethod::Cash->value;
        $this->viewModeSessionKey = Auth::id().'_pharmacy_pos_view_mode';
        $this->viewMode = Session::get($this->viewModeSessionKey, 'card');
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
        $this->selectedPatientId = $id;
        $this->patientSearch = '';
        $this->patientResults = collect();
        $this->saveCartToCache();
    }

    public function table(Table $table): Table
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
            ->actions([
                Action::make('add_to_cart')
                    ->action(fn (Medication $record) => $this->addToCart($record->id)),
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
                TextColumn::make('name_display')
                    ->label(__('Product'))
                    ->formatStateUsing(fn (?Medication $record) => $record?->service?->name ?? $record?->generic_name)
                    ->size('lg')
                    ->weight('bold')
                    ->alignStart()
                    ->extraAttributes(['class' => 'group-hover:text-primary-600 dark:group-hover:text-primary-400 transition']),
                TextColumn::make('generic_name')
                    ->label(__('Generic'))
                    ->alignStart()
                    ->color('gray')
                    ->size('sm')
                    ->visible(fn (?Medication $record): bool => filled($record?->generic_name) && ($record?->service?->name !== $record?->generic_name)),
                TextColumn::make('strength')
                    ->alignStart()
                    ->color('gray')
                    ->size('xs')
                    ->placeholder('—'),
                TextColumn::make('price_stock_row')
                    ->label('')
                    ->formatStateUsing(function (?Medication $record): string {
                        $price = number_format($record?->service?->price ?? 0, 2);
                        $qty = (int) ($record?->stockItems?->sum('quantity_on_hand') ?? 0);
                        $badgeClass = $qty > 10
                            ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                            : ($qty > 0
                                ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'
                                : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400');

                        $currency = config('core.default_currency');

                        return '<div class="flex items-center justify-between mt-2">'
                            ."<span class=\"text-lg font-bold text-primary-600 dark:text-primary-400\">{$currency} {$price}</span>"
                            ."<span class=\"text-xs px-2 py-0.5 rounded-full {$badgeClass}\">"
                            .($qty > 0 ? "{$qty} ".__('in stock') : __('Out of stock'))
                            .'</span>'
                            .'</div>';
                    })
                    ->html(),
            ])->extraAttributes(['class' => 'p-4 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm hover:shadow-md transition cursor-pointer group']),
        ];
    }

    /**
     * @return array<int, TextColumn>
     */
    protected function getMedicationTableColumns(): array
    {
        return [
            TextColumn::make('service.name')
                ->label(__('Name'))
                ->weight('bold')
                ->description(fn (?Medication $record): ?string => filled($record->service?->name) && $record->generic_name !== $record->service?->name
                    ? $record->generic_name
                    : null)
                ->formatStateUsing(fn (?Medication $record) => $record?->service?->name ?? $record?->generic_name),
            TextColumn::make('strength')
                ->label(__('Strength'))
                ->placeholder('—'),
            TextColumn::make('service.price')
                ->label(__('Price'))
                ->money(config('core.default_currency'))
                ->alignRight()
                ->weight('bold'),
            TextColumn::make('stock_qty')
                ->label(__('Stock'))
                ->state(fn (?Medication $record): int => (int) $record?->stockItems?->sum('quantity_on_hand'))
                ->badge()
                ->alignCenter()
                ->color(fn ($state): string => match (true) {
                    (int) $state > 10 => 'success',
                    (int) $state > 0 => 'warning',
                    default => 'danger',
                })
                ->formatStateUsing(fn ($state): string => (string) (int) $state),
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
            ->with(['service', 'stockItems' => fn ($q) => $q->where('branch_id', $this->selectedBranchId)])
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

        if ($this->cart->has($medicationId)) {
            $item = $this->cart->get($medicationId);
            if ($item['quantity'] >= $stockQty) {
                Notification::make()
                    ->danger()
                    ->title(__('Stock limit reached'))
                    ->send();

                return;
            }
            $item['quantity']++;
            $this->cart->put($medicationId, $item);
        } else {
            $this->cart[$medicationId] = [
                'id' => $medication->id,
                'name' => $medication->service?->name ?? $medication->generic_name,
                'generic_name' => $medication->generic_name,
                'strength' => $medication->strength,
                'price' => (float) ($medication->service?->price ?? 0),
                'quantity' => 1,
                'available' => $stockQty,
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

        $stockQty = StockItem::query()
            ->where('medication_id', $id)
            ->where('branch_id', $this->selectedBranchId)
            ->sum('quantity_on_hand');

        if ($quantity > $stockQty) {
            Notification::make()
                ->danger()
                ->title(__('Insufficient stock'))
                ->send();

            return;
        }

        $item = $this->cart->get($id);
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
                    'medication_id' => $item['id'],
                    'quantity' => $item['quantity'],
                ])->values()->toArray(),
                'payment_method' => PaymentMethod::from($this->paymentMethod),
                'pos_discount_amount' => $discountStr,
                'amount_tendered' => $amountTendered,
            ]);

            Notification::make()
                ->title(__('Checkout successful'))
                ->body(__('Invoice:').' '.$result['invoice']->invoice_number)
                ->success()
                ->send();

            $this->resetState();
        } catch (\Throwable $e) {
            Notification::make()
                ->title(__('Checkout failed'))
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
}
