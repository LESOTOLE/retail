<?php

namespace App\Models;

use App\Enums\ChatSender;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sesi percakapan AI Assistant (PRD 5 - ai_chat_sessions).
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $session_token
 * @property int|null $last_context_vehicle_id
 */
class AiChatSession extends Model
{
    /** @use HasFactory<\Database\Factories\AiChatSessionFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'session_token',
        'last_context_vehicle_id',
    ];

    public function getRouteKeyName(): string
    {
        return 'session_token';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Kendaraan terakhir yang menjadi konteks percakapan.
     *
     * @return BelongsTo<Vehicle, $this>
     */
    public function contextVehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'last_context_vehicle_id');
    }

    /**
     * Seluruh pesan pada sesi ini (cascade delete).
     *
     * @return HasMany<AiChatMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(AiChatMessage::class, 'session_id');
    }

    /**
     * Menyimpan satu pesan ke riwayat percakapan.
     *
     * @param  array<string, mixed>|null  $rawPayload
     */
    public function pushMessage(ChatSender $sender, string $message, ?array $rawPayload = null): AiChatMessage
    {
        return $this->messages()->create([
            'sender' => $sender,
            'message' => $message,
            'raw_payload' => $rawPayload,
        ]);
    }
}
