<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi order kasir toko offline / POS direct billing (PRD 4.5 & 6.8).
 */
class PosOrderRequest extends FormRequest
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
            'customer_name' => ['nullable', 'string', 'max:150'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'payment_method' => ['required', 'string', 'in:CASH,QRIS,DEBIT,TRANSFER'],
            'payment_reference' => ['nullable', 'string', 'max:100'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.sku' => ['required', 'string', 'exists:product_variants,sku'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, int>
     */
    public function cartItems(): array
    {
        $items = [];
        foreach ($this->validated('items', []) as $line) {
            $sku = (string) $line['sku'];
            $qty = (int) $line['quantity'];
            $items[$sku] = ($items[$sku] ?? 0) + $qty;
        }

        return $items;
    }
}
