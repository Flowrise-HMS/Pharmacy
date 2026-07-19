<?php

namespace Modules\Pharmacy\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Modules\Core\Http\Controllers\Api\ApiController;
use Modules\Core\Http\Responses\ApiResponse;
use Modules\Pharmacy\Http\Resources\DrugTransformer;
use Modules\Pharmacy\Models\Drug;

class DrugController extends ApiController
{
    /**
     * @group Drugs
     */
    public function index(): JsonResponse
    {
        $this->authorizeApi('viewAny', Drug::class);

        return ApiResponse::paginated(
            Drug::query(),
            DrugTransformer::class,
        );
    }

    /**
     * @group Drugs
     */
    public function show(string $id): JsonResponse
    {
        $drug = Drug::query()->findOrFail($id);

        $this->authorizeApi('view', $drug);

        return ApiResponse::ok(new DrugTransformer($drug));
    }
}
