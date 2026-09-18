<?php

namespace App\Enums;

/**
 * Pengirim pesan pada percakapan AI sesuai ERD PRD bagian 5
 * (ai_chat_messages.sender).
 */
enum ChatSender: string
{
    case User = 'user';
    case Assistant = 'assistant';
    case System = 'system';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Pemetaan ke role yang dipahami Gemini generateContent API.
     */
    public function toGeminiRole(): string
    {
        return match ($this) {
            self::User, self::System => 'user',
            self::Assistant => 'model',
        };
    }
}
