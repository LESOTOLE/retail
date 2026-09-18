<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiChatSession;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Audit Log & Sesi Percakapan AI Assistant oleh Super Admin (PRD 6.8 & 9.1).
 */
class AiLogController extends Controller
{
    /**
     * GET /api/v1/admin/ai/logs
     */
    public function index(Request $request): JsonResponse
    {
        $paginator = AiChatSession::query()
            ->with(['user:id,name,email', 'contextVehicle:id,brand,model,year_start,year_end', 'messages'])
            ->withCount('messages')
            ->latest('id')
            ->paginate((int) $request->integer('per_page', 15))
            ->withQueryString();

        $data = $paginator->through(function (AiChatSession $session) {
            return [
                'id' => $session->id,
                'session_token' => $session->session_token,
                'user' => $session->user ? [
                    'id' => $session->user->id,
                    'name' => $session->user->name,
                    'email' => $session->user->email,
                ] : null,
                'context_vehicle' => $session->contextVehicle ? [
                    'id' => $session->contextVehicle->id,
                    'name' => $session->contextVehicle->full_name,
                ] : null,
                'messages_count' => $session->messages_count,
                'messages' => $session->messages->map(fn ($m) => [
                    'id' => $m->id,
                    'sender' => $m->sender->value,
                    'message' => $m->message,
                    'raw_payload' => $m->raw_payload,
                    'created_at' => $m->created_at?->toIso8601String(),
                ]),
                'created_at' => $session->created_at?->toIso8601String(),
            ];
        });

        return ApiResponse::paginated(
            $paginator,
            null,
            'Audit log AI berhasil diambil'
        );
    }
}
