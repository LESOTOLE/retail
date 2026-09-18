<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CartValidateRequest;
use App\Services\InventoryService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Validasi ketersediaan stok sebelum checkout (PRD 6.3).
 */
class CartController extends Controller
{
    public function __construct(
        protected InventoryService $inventory
    ) {}

    /**
     * POST /api/v1/cart/validate
     */
    public function validateCart(CartValidateRequest $request): JsonResponse
    {
        $result = $this->inventory->validateCart($request->cartItems());

        if (! $result['valid']) {
            return response()->json([
                'success' => false,
                'message' => 'Sebagian item tidak tersedia dengan jumlah yang diminta.',
                'data' => [
                    'valid' => false,
                    'total_amount' => $result['total_amount'],
                    'items' => $result['items'],
                ],
                'errors' => $result['issues'],
            ], HttpResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        return ApiResponse::success([
            'valid' => true,
            'total_amount' => $result['total_amount'],
            'items' => $result['items'],
        ], 'Seluruh item tersedia dan siap di-checkout');
    }
}
