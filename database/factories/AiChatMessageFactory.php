<?php

namespace Database\Factories;

use App\Enums\ChatSender;
use App\Models\AiChatMessage;
use App\Models\AiChatSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiChatMessage>
 */
class AiChatMessageFactory extends Factory
{
    protected $model = AiChatMessage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'session_id' => AiChatSession::factory(),
            'sender' => ChatSender::User,
            'message' => fake()->sentence(),
            'raw_payload' => null,
        ];
    }

    public function fromAssistant(): static
    {
        return $this->state(fn (array $attributes) => [
            'sender' => ChatSender::Assistant,
        ]);
    }
}
