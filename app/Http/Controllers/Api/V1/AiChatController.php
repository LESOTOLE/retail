<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AiChatRequest;
use App\Models\AiChatSession;
use App\Models\Vehicle;
use App\Services\AI\GeminiAIService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

/**
 * AI Sales & Compatibility Assistant Endpoint (PRD 4.3 & 6.7).
 */
class AiChatController extends Controller
{
    public function __construct(
        protected GeminiAIService $aiService
    ) {}

    /**
     * POST /api/v1/ai/chat
     */
    public function chat(AiChatRequest $request): JsonResponse
    {
        $sessionToken = $request->validated('session_token') ?: (string) Str::uuid();

        $session = AiChatSession::firstOrCreate(
            ['session_token' => $sessionToken],
            ['user_id' => $request->user()?->id]
        );

        if ($request->user() && ! $session->user_id) {
            $session->user_id = $request->user()->id;
            $session->save();
        }

        $vehicle = null;
        if ($request->filled('vehicle_id')) {
            $vehicle = Vehicle::find($request->validated('vehicle_id'));
            if ($vehicle) {
                $session->last_context_vehicle_id = $vehicle->id;
                $session->save();
            }
        } elseif ($session->last_context_vehicle_id) {
            $vehicle = $session->contextVehicle;
        }

        $result = $this->aiService->handle(
            $session,
            $request->validated('message'),
            $vehicle
        );

        return ApiResponse::success([
            'session_token' => $session->session_token,
            'vehicle_context' => $vehicle ? [
                'id' => $vehicle->id,
                'name' => $vehicle->full_name,
            ] : null,
            'reply' => $result['reply'],
            'recommended_products' => $result['recommended_products'],
            'tool_calls' => $result['tool_calls'],
            'driver' => $result['driver'],
        ], 'AI reply generated');
    }
}
