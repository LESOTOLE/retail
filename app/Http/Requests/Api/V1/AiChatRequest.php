<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi payload AI Assistant (PRD 6.4).
 */
class AiChatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'session_token' => ['nullable', 'string', 'max:64'],
            'vehicle_id' => ['nullable', 'integer', 'exists:vehicles,id'],
            'message' => ['required', 'string', 'min:2', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'message.required' => 'Pesan tidak boleh kosong.',
            'vehicle_id.exists' => 'Kendaraan tidak ditemukan pada master data.',
        ];
    }
}
