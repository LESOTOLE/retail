<?php

namespace Database\Factories;

use App\Models\AiChatSession;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AiChatSession>
 */
class AiChatSessionFactory extends Factory
{
    protected $model = AiChatSession::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'session_token' => (string) Str::uuid(),
            'last_context_vehicle_id' => null,
        ];
    }
}
