<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Katalog kategori produk (PRD 6.2).
 */
class CategoryController extends Controller
{
    /**
     * GET /api/v1/categories
     */
    public function index(): JsonResponse
    {
        $categories = Category::query()
            ->withCount(['products' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('name')
            ->get();

        return ApiResponse::success(
            CategoryResource::collection($categories)->resolve(),
            'Daftar kategori berhasil diambil'
        );
    }
}
