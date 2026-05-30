<?php

namespace Modules\Pharmacy\Tests\Unit;

use Modules\Core\Enums\UnitCategory;
use Modules\Core\Models\Unit;
use Modules\Pharmacy\Classes\Support\MedicationQuantityValidator;
use Modules\Pharmacy\Models\Medication;
use Tests\TestCase;

class MedicationQuantityValidatorTest extends TestCase
{
    private MedicationQuantityValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('module:migrate', ['module' => 'Core', '--force' => true]);
        $this->seed(\Modules\Core\Database\Seeders\UnitSeeder::class);

        $this->validator = new MedicationQuantityValidator;
    }

    public function test_assert_stock_quantity_accepts_integer_for_non_fractional_unit(): void
    {
        $tablet = Unit::where('code', 'tablet')->first();
        $medication = $this->createMock(Medication::class);
        $medication->method('__get')->with('stockUnit')->willReturn($tablet);

        $this->validator->assertStockQuantity($medication, 5, 'stock');

        $this->expectNotToPerformAssertions();
    }

    public function test_assert_stock_quantity_rejects_float_for_non_fractional_unit(): void
    {
        $tablet = Unit::where('code', 'tablet')->first();
        $medication = $this->createMock(Medication::class);
        $medication->method('__get')->with('stockUnit')->willReturn($tablet);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tablet quantities must be whole numbers');

        $this->validator->assertStockQuantity($medication, 2.5, 'stock');
    }

    public function test_assert_stock_quantity_accepts_float_for_fractional_unit(): void
    {
        $ml = Unit::where('code', 'ml')->first();

        $this->assertTrue($ml->is_fractional);

        $medication = $this->createMock(Medication::class);
        $medication->method('__get')->with('stockUnit')->willReturn($ml);

        $this->validator->assertStockQuantity($medication, 1.5, 'stock');

        $this->expectNotToPerformAssertions();
    }

    public function test_assert_stock_quantity_rejects_negative(): void
    {
        $tablet = Unit::where('code', 'tablet')->first();
        $medication = $this->createMock(Medication::class);
        $medication->method('__get')->with('stockUnit')->willReturn($tablet);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Quantity cannot be negative');

        $this->validator->assertStockQuantity($medication, -1, 'stock');
    }

    public function test_assert_stock_quantity_skips_when_no_unit(): void
    {
        $medication = $this->createMock(Medication::class);

        $this->validator->assertStockQuantity($medication, 5, 'stock');

        $this->expectNotToPerformAssertions();
    }

    public function test_assert_billing_quantity_accepts_valid(): void
    {
        $this->validator->assertBillingQuantity(1);

        $this->expectNotToPerformAssertions();
    }

    public function test_assert_billing_quantity_rejects_zero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Billing quantity must be at least 1');

        $this->validator->assertBillingQuantity(0);
    }

    public function test_assert_dose_does_not_exceed_remaining(): void
    {
        $this->validator->assertDoseDoesNotExceedRemaining(3, 5);

        $this->expectNotToPerformAssertions();
    }

    public function test_assert_dose_exceeds_remaining_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Dose amount 6 exceeds remaining 5 dose(s)');

        $this->validator->assertDoseDoesNotExceedRemaining(6, 5);
    }

    public function test_assert_units_match_passes_when_both_null(): void
    {
        $this->validator->assertUnitsMatch(null, null, 'test');

        $this->expectNotToPerformAssertions();
    }

    public function test_assert_units_match_passes_when_matching(): void
    {
        $tablet = Unit::where('code', 'tablet')->first();

        $this->validator->assertUnitsMatch($tablet, $tablet, 'test');

        $this->expectNotToPerformAssertions();
    }

    public function test_assert_units_match_throws_on_mismatch(): void
    {
        $tablet = Unit::where('code', 'tablet')->first();
        $capsule = Unit::where('code', 'capsule')->first();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('expected unit');

        $this->validator->assertUnitsMatch($tablet, $capsule, 'test');
    }

    public function test_assert_units_match_skips_when_expected_null(): void
    {
        $capsule = Unit::where('code', 'capsule')->first();

        $this->validator->assertUnitsMatch(null, $capsule, 'test');

        $this->expectNotToPerformAssertions();
    }

    public function test_assert_units_match_skips_when_actual_null(): void
    {
        $tablet = Unit::where('code', 'tablet')->first();

        $this->validator->assertUnitsMatch($tablet, null, 'test');

        $this->expectNotToPerformAssertions();
    }
}
