<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi payload keranjang (PRD 6.3 - POST /cart/validate & /orders/checkout).
 */
class CartValidateRequest extends FormRequest
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
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.sku' => ['required', 'string', 'exists:product_variants,sku'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'shipping_address' => ['nullable', 'string', 'max:500'],
            'destination_postal_code' => ['nullable', 'string', 'max:10'],
            'courier_code' => ['nullable', 'string', 'max:50'],
            'courier_service' => ['nullable', 'string', 'max:50'],
            'shipping_cost' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'Keranjang tidak boleh kosong.',
            'items.*.sku.exists' => 'SKU tidak ditemukan pada katalog.',
        ];
    }

    /**
     * Item keranjang yang sudah ternormalisasi (SKU digabung bila duplikat).
     *
     * @return array<string, int>  [sku => total quantity]
     */
    public function cartItems(): array
    {
        $normalized = [];

        foreach ($this->validated()['items'] as $item) {
            $sku = $item['sku'];
            $normalized[$sku] = ($normalized[$sku] ?? 0) + (int) $item['quantity'];
        }

        return $normalized;
    }
}
