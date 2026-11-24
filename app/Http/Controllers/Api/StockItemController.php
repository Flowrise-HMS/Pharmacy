<?php

namespace Modules\Pharmacy\Http\Controllers\Api;

use Modules\Core\Http\Controllers\Api\ResourceApiController;
use Modules\Pharmacy\Models\StockItem;

/**
 * @group Pharmacy
 *
 * Dispensary stock on hand per medication (read-only; stock moves through
 * dispensing, POS sales and the Inventory issue-to-pharmacy bridge).
 */
class StockItemController extends ResourceApiController
{
    protected string $defaultSort = 'quantity_on_hand';

    public static function model(): string
    {
        return StockItem::class;
    }

    public static function label(): string
    {
        return 'dispensary stock item';
    }

    public function fields(): array
    {
        return ['id', 'medication_id', 'quantity_on_hand', 'reorder_point', 'updated_at'];
    }

    public function filters(): array
    {
        return ['medication_id' => 'medication_id'];
    }

    public function sortable(): array
    {
        return ['quantity_on_hand', 'updated_at'];
    }
}
