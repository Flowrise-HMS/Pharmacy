<?php

namespace Modules\Pharmacy\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Clinical\Models\RequestItem;
use Modules\Pharmacy\Classes\Services\PrescriptionSlipPresenter;

class PrescriptionSlipController extends Controller
{
    public function show(Request $request, RequestItem $requestItem, PrescriptionSlipPresenter $presenter): View
    {
        return $this->renderCombined($request, $presenter, [$requestItem->id]);
    }

    public function showCombined(Request $request, PrescriptionSlipPresenter $presenter): View
    {
        $items = $request->input('items', []);

        if (is_string($items)) {
            $items = array_filter(explode(',', $items));
        }

        if (! is_array($items)) {
            $items = [];
        }

        return $this->renderCombined($request, $presenter, $items);
    }

    /**
     * @param  array<int, string>  $itemIds
     */
    protected function renderCombined(Request $request, PrescriptionSlipPresenter $presenter, array $itemIds): View
    {
        $data = $presenter->present($itemIds, $request->user(), requireOutsideDispense: true);

        return view('pharmacy::prescription-slip', $data);
    }
}
