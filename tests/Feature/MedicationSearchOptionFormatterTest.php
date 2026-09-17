<?php

namespace Modules\Pharmacy\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Core\Models\Branch;
use Modules\Pharmacy\Classes\Services\MedicationSearchOptionFormatter;
use Modules\Pharmacy\Models\Drug;
use Modules\Pharmacy\Models\Medication;
use Modules\Pharmacy\Models\StockItem;
use Tests\TestCase;

class MedicationSearchOptionFormatterTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Pharmacy']);
    }

    public function test_catalog_rows_come_first_with_branch_scoped_stock_badges(): void
    {
        $branch = Branch::factory()->create();
        $otherBranch = Branch::factory()->create();

        $stocked = Medication::factory()->create(['generic_name' => 'Metformin', 'brand_name' => null, 'strength' => '500mg', 'rxnorm_code' => '600001', 'ndc_code' => null]);
        StockItem::factory()->create(['medication_id' => $stocked->id, 'branch_id' => $branch->id, 'quantity_on_hand' => 42]);
        StockItem::factory()->create(['medication_id' => $stocked->id, 'branch_id' => $otherBranch->id, 'quantity_on_hand' => 500]);

        $empty = Medication::factory()->create(['generic_name' => 'Metformin', 'brand_name' => null, 'strength' => '850mg', 'rxnorm_code' => '600002', 'ndc_code' => null]);

        $reference = Drug::factory()->create([
            'source_provider' => 'local',
            'generic_name' => 'Metformin',
            'display_name' => 'Metformin 1000 MG Tablet',
            'rxnorm_code' => '600003',
            'ndc_code' => null,
        ]);

        $options = app(MedicationSearchOptionFormatter::class)->searchOptions('metformin', $branch->id);
        $keys = array_keys($options);

        $this->assertContains($stocked->service_id, $keys);
        $this->assertContains($empty->service_id, $keys);
        $this->assertContains('drug:'.$reference->id, $keys);
        $this->assertSame('drug:'.$reference->id, end($keys), 'reference drugs come after catalog rows');

        $this->assertStringContainsString('[Catalog]', $options[$stocked->service_id]);
        $this->assertStringContainsString('fi-color-success', $options[$stocked->service_id]);
        $this->assertStringContainsString('42 in stock', $options[$stocked->service_id]);
        $this->assertStringNotContainsString('500 in stock', $options[$stocked->service_id]);

        $this->assertStringContainsString('fi-color-danger', $options[$empty->service_id]);
        $this->assertStringContainsString('Out of stock', $options[$empty->service_id]);

        $this->assertStringContainsString('[Reference]', $options['drug:'.$reference->id]);
        $this->assertStringContainsString('Not stocked', $options['drug:'.$reference->id]);
    }

    public function test_unknown_branch_shows_stock_unknown_rather_than_another_branch_count(): void
    {
        $branch = Branch::factory()->create();
        $medication = Medication::factory()->create(['generic_name' => 'Lisinopril', 'brand_name' => null, 'rxnorm_code' => '600010', 'ndc_code' => null]);
        StockItem::factory()->create(['medication_id' => $medication->id, 'branch_id' => $branch->id, 'quantity_on_hand' => 77]);

        $options = app(MedicationSearchOptionFormatter::class)->searchOptions('lisinopril', null);

        $this->assertStringContainsString('Stock unknown', $options[$medication->service_id]);
        $this->assertStringNotContainsString('77', $options[$medication->service_id]);
        $this->assertStringNotContainsString('Out of stock', $options[$medication->service_id]);
    }

    public function test_non_formulary_rows_are_labelled_needs_pricing(): void
    {
        $medication = Medication::factory()->nonFormulary()->create(['generic_name' => 'Atorvastatin', 'brand_name' => null, 'rxnorm_code' => '600020', 'ndc_code' => null]);

        $options = app(MedicationSearchOptionFormatter::class)->searchOptions('atorvastatin', null);

        $this->assertStringContainsString('[Needs pricing]', $options[$medication->service_id]);
    }

    public function test_labels_escape_user_controlled_names(): void
    {
        $medication = Medication::factory()->create([
            'generic_name' => 'Evil<script>alert(1)</script>',
            'brand_name' => null,
            'rxnorm_code' => '600030',
            'ndc_code' => null,
        ]);

        $options = app(MedicationSearchOptionFormatter::class)->searchOptions('evil', null);
        $label = $options[$medication->service_id];

        $this->assertStringNotContainsString('<script>', $label);
        $this->assertStringContainsString('&lt;script&gt;', $label);
    }

    public function test_option_label_resolves_every_value_shape(): void
    {
        $branch = Branch::factory()->create();
        $formatter = app(MedicationSearchOptionFormatter::class);

        $withService = Medication::factory()->create(['generic_name' => 'Omeprazole', 'brand_name' => null, 'rxnorm_code' => '600040', 'ndc_code' => null]);
        StockItem::factory()->create(['medication_id' => $withService->id, 'branch_id' => $branch->id, 'quantity_on_hand' => 5]);
        $withoutService = Medication::factory()->create(['service_id' => null, 'generic_name' => 'Ranitidine', 'brand_name' => null, 'rxnorm_code' => '600041', 'ndc_code' => null]);
        $drug = Drug::factory()->create(['source_provider' => 'rxnorm', 'display_name' => 'Famotidine 20 MG Tablet', 'rxnorm_code' => '600042', 'ndc_code' => null]);

        $serviceLabel = $formatter->optionLabel($withService->service_id, $branch->id);
        $this->assertStringContainsString('[Catalog]', $serviceLabel);
        $this->assertStringContainsString('5 in stock', $serviceLabel);

        $medicationLabel = $formatter->optionLabel('medication:'.$withoutService->id, $branch->id);
        $this->assertStringContainsString('Ranitidine', $medicationLabel);
        $this->assertStringContainsString('Out of stock', $medicationLabel);

        $drugLabel = $formatter->optionLabel('drug:'.$drug->id, $branch->id);
        $this->assertStringContainsString('[External] Famotidine 20 MG Tablet', $drugLabel);

        $this->assertNull($formatter->optionLabel('drug:does-not-exist', $branch->id));
        $this->assertNull($formatter->optionLabel(null));
    }
}
