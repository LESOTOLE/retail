<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Helper terpusat untuk format response JSON standar MotoVault (PRD bagian 6):
 *
 * {
 *   "success": true,
 *   "message": "Deskripsi respon",
 *   "data": {},
 *   "errors": null
 * }
 */
class ApiResponse
{
    /**
     * Response sukses generik.
     */
    public static function success(
        mixed $data = null,
        string $message = 'Success',
        int $status = HttpResponse::HTTP_OK,
        array $extra = []
    ): JsonResponse {
        return response()->json(array_merge([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'errors' => null,
        ], $extra), $status);
    }

    /**
     * Response sukses untuk resource yang baru dibuat.
     */
    public static function created(mixed $data = null, string $message = 'Resource created'): JsonResponse
    {
        return self::success($data, $message, HttpResponse::HTTP_CREATED);
    }

    /**
     * Response gagal generik.
     */
    public static function error(
        string $message = 'Something went wrong',
        mixed $errors = null,
        int $status = HttpResponse::HTTP_BAD_REQUEST,
        ?string $errorCode = null
    ): JsonResponse {
        $payload = [
            'success' => false,
            'message' => $message,
            'data' => null,
            'errors' => $errors,
        ];

        if ($errorCode !== null) {
            $payload['error_code'] = $errorCode;
        }

        return response()->json($payload, $status);
    }

    /**
     * Response untuk data terpaginasi; meta pagination dipisah dari payload data
     * agar kontrak "data" tetap berupa list murni.
     */
    public static function paginated(
        LengthAwarePaginator $paginator,
        ?ResourceCollection $collection = null,
        string $message = 'Data retrieved successfully'
    ): JsonResponse {
        $items = $collection
            ? $collection->resolve()
            : $paginator->items();

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
            'errors' => null,
        ]);
    }
}
