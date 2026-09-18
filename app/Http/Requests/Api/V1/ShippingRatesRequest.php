<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi request tarif pengiriman ekspedisi (PRD Phase 2 - Feature 3).
 */
class ShippingRatesRequest extends FormRequest
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
            'destination_postal_code' => ['required', 'string', 'max:10'],
            'origin_postal_code' => ['nullable', 'string', 'max:10'],
            'courier' => ['nullable', 'string', 'in:jne,jnt,sicepat,gosend'],
            'items' => ['nullable', 'array'],
            'items.*.variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1'],
            'items.*.weight_gram' => ['nullable', 'integer', 'min:1'],
            'items.*.length_cm' => ['nullable', 'numeric', 'min:0'],
            'items.*.width_cm' => ['nullable', 'numeric', 'min:0'],
            'items.*.height_cm' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
