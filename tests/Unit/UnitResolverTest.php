<?php

namespace Modules\Pharmacy\Tests\Unit;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Core\Enums\UnitCategory;
use Modules\Core\Models\Unit;
use Modules\Pharmacy\Classes\Support\UnitResolver;
use Modules\Pharmacy\Enums\DosageForm;
use Tests\TestCase;

class UnitResolverTest extends TestCase
{
    use DatabaseTransactions;

    private UnitResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core']);
        $this->seed(\Modules\Core\Database\Seeders\UnitSeeder::class);

        $this->resolver = new UnitResolver;
    }

    public function test_returns_nulls_for_null_dosage_form(): void
    {
        $result = $this->resolver->defaultsForDosageForm(null);

        $this->assertNull($result['stock_unit']);
        $this->assertNull($result['billing_unit']);
        $this->assertNull($result['dose_unit']);
    }

    public function test_returns_tablet_units_for_tablet_dosage_form(): void
    {
        $result = $this->resolver->defaultsForDosageForm(DosageForm::TABLET);

        $this->assertNotNull($result['stock_unit']);
        $this->assertSame('tablet', $result['stock_unit']->code);
        $this->assertSame('tablet', $result['billing_unit']->code);
        $this->assertSame('tablet', $result['dose_unit']->code);
    }

    public function test_returns_vial_and_ml_units_for_injection(): void
    {
        $result = $this->resolver->defaultsForDosageForm(DosageForm::INJECTION);

        $this->assertSame('vial', $result['stock_unit']->code);
        $this->assertSame('vial', $result['billing_unit']->code);
        $this->assertSame('ml', $result['dose_unit']->code);
    }

    public function test_returns_bottle_and_ml_units_for_syrup(): void
    {
        $result = $this->resolver->defaultsForDosageForm(DosageForm::SYRUP);

        $this->assertSame('bottle', $result['stock_unit']->code);
        $this->assertSame('bottle', $result['billing_unit']->code);
        $this->assertSame('ml', $result['dose_unit']->code);
    }

    public function test_returns_each_unit_for_suppository(): void
    {
        $result = $this->resolver->defaultsForDosageForm(DosageForm::SUPPOSITORY);

        $this->assertSame('each', $result['stock_unit']->code);
        $this->assertSame('each', $result['billing_unit']->code);
        $this->assertSame('each', $result['dose_unit']->code);
    }

    public function test_format_quantity_with_unit(): void
    {
        $unit = Unit::where('code', 'tablet')->first();

        $this->assertSame('2 Tablet', $this->resolver->formatQuantity(2, $unit));
    }

    public function test_format_quantity_with_null_unit(): void
    {
        $this->assertSame('5', $this->resolver->formatQuantity(5, null));
    }

    public function test_format_quantity_with_fractional_value(): void
    {
        $ml = Unit::where('code', 'ml')->first();

        $this->assertSame('1.5 Milliliter', $this->resolver->formatQuantity(1.5, $ml));
    }

    public function test_format_quantity_short_with_code(): void
    {
        $unit = Unit::where('code', 'tablet')->first();

        $this->assertSame('2 tablet', $this->resolver->formatQuantityShort(2, $unit));
    }

    public function test_unit_label_returns_label_when_unit_present(): void
    {
        $unit = Unit::where('code', 'tablet')->first();

        $this->assertSame('Tablet', $this->resolver->unitLabel($unit));
    }

    public function test_unit_label_returns_default_when_null(): void
    {
        $this->assertSame('units', $this->resolver->unitLabel(null));
    }
}
