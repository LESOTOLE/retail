<?php

namespace App\Models;

use App\Enums\ChatSender;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Riwayat pesan & audit log function calling AI (PRD 5 - ai_chat_messages).
 *
 * @property int $id
 * @property int $session_id
 * @property ChatSender $sender
 * @property string $message
 * @property array<string, mixed>|null $raw_payload
 */
class AiChatMessage extends Model
{
    /** @use HasFactory<\Database\Factories\AiChatMessageFactory> */
    use HasFactory;

    protected $fillable = [
        'session_id',
        'sender',
        'message',
        'raw_payload',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sender' => ChatSender::class,
            'raw_payload' => 'array',
        ];
    }

    /**
     * @return BelongsTo<AiChatSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(AiChatSession::class, 'session_id');
    }
}
