<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AiChatRequest;
use App\Models\AiChatSession;
use App\Models\Vehicle;
use App\Services\AI\GeminiStreamingService;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Controller untuk endpoint AI Streaming SSE (PRD Phase 2 - Feature 1).
 */
class AiStreamingChatController extends Controller
{
    public function __construct(
        protected GeminiStreamingService $streamingService
    ) {}

    /**
     * POST /api/v1/ai/chat/stream
     */
    public function stream(AiChatRequest $request): StreamedResponse
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

        return $this->streamingService->stream(
            $session,
            $request->validated('message'),
            $vehicle
        );
    }
}
