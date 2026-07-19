<?php

namespace Modules\Pharmacy\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\Controllers\Api\ApiController;
use Modules\Core\Http\Responses\ApiResponse;
use Modules\Pharmacy\Http\Resources\DispenseTransformer;
use Modules\Pharmacy\Models\Dispense;

class DispenseController extends ApiController
{
    /**
     * @group Dispensing
     */
    public function index(): JsonResponse
    {
        $this->authorizeApi('viewAny', Dispense::class);

        return ApiResponse::paginated(
            Dispense::query()->latest('dispensed_at'),
            DispenseTransformer::class,
        );
    }

    /**
     * @group Dispensing
     */
    public function show(string $id): JsonResponse
    {
        $dispense = Dispense::query()->findOrFail($id);

        $this->authorizeApi('view', $dispense);

        return ApiResponse::ok(new DispenseTransformer($dispense));
    }

    /**
     * @group Dispensing
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $dispense = Dispense::query()->findOrFail($id);

        $this->authorizeApi('update', $dispense);

        $validated = $request->validate([
            'branch_id' => ['sometimes', 'required', 'uuid', 'exists:branches,id'],
            'batch_number' => ['sometimes', 'nullable', 'string', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'quantity' => ['sometimes', 'integer', 'min:0'],
        ]);

        $dispense->update($validated);

        return ApiResponse::ok(new DispenseTransformer($dispense));
    }
}
