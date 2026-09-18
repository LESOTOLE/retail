<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi sinkronisasi kompatibilitas kendaraan produk (PRD 6.8).
 */
class AdminCompatibilitySyncRequest extends FormRequest
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
            'vehicles' => ['required', 'array', 'min:1'],
            'vehicles.*.vehicle_id' => ['required', 'integer', 'exists:vehicles,id'],
            'vehicles.*.notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
