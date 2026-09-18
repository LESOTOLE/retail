<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StockTransferCreateRequest;
use App\Http\Requests\Api\V1\StockTransferStatusUpdateRequest;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use App\Services\Warehouse\StockTransferService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller Manajemen Mutasi & Transfer Stok Antar Gudang (Admin/Staff).
 */
class StockTransferAdminController extends Controller
{
    public function __construct(
        protected StockTransferService $transferService
    ) {}

    /**
     * GET /api/v1/admin/warehouses/transfers
     * Daftar seluruh riwayat transfer stok.
     */
    public function index(Request $request): JsonResponse
    {
        $query = StockTransfer::query()
            ->with(['fromWarehouse:id,code,name', 'toWarehouse:id,code,name', 'items.variant.product', 'creator:id,name'])
            ->latest('id');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $transfers = $query->paginate((int) $request->integer('per_page', 15));

        return ApiResponse::paginated($transfers, null, 'Daftar transfer stok berhasil diambil');
    }

    /**
     * POST /api/v1/admin/warehouses/transfers
     * Buat mutasi transfer stok baru.
     */
    public function store(StockTransferCreateRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $from = Warehouse::findOrFail($validated['from_warehouse_id']);
        $to = Warehouse::findOrFail($validated['to_warehouse_id']);

        $transfer = $this->transferService->createTransfer(
            fromWarehouse: $from,
            toWarehouse: $to,
            items: $validated['items'],
            creator: $request->user(),
            notes: $validated['notes'] ?? null
        );

        return ApiResponse::created($transfer, 'Permintaan transfer stok berhasil dibuat.');
    }

    /**
     * GET /api/v1/admin/warehouses/transfers/{id}
     * Detail mutasi transfer stok.
     */
    public function show(int $id): JsonResponse
    {
        $transfer = StockTransfer::with([
            'fromWarehouse',
            'toWarehouse',
            'items.variant.product',
            'creator:id,name',
        ])->findOrFail($id);

        return ApiResponse::success($transfer, 'Detail transfer stok berhasil diambil');
    }

    /**
     * PATCH /api/v1/admin/warehouses/transfers/{id}/status
     * Ubah status transfer stok (dispatch, complete, cancel).
     */
    public function updateStatus(StockTransferStatusUpdateRequest $request, int $id): JsonResponse
    {
        $transfer = StockTransfer::findOrFail($id);
        $action = $request->validated('action');

        $updatedTransfer = match ($action) {
            'dispatch' => $this->transferService->dispatchTransfer($transfer),
            'complete' => $this->transferService->completeTransfer($transfer),
            'cancel' => $this->transferService->cancelTransfer($transfer),
        };

        return ApiResponse::success($updatedTransfer, "Status transfer stok berhasil diperbarui ({$action}).");
    }
}
