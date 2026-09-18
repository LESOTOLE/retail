<?php

namespace App\Http\Resources;

use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrderItem
 */
class OrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->whenLoaded('variant', fn () => $this->variant->sku),
            'variant_name' => $this->whenLoaded('variant', fn () => $this->variant->variant_name),
            'product_name' => $this->whenLoaded(
                'variant',
                fn () => $this->variant->relationLoaded('product') ? $this->variant->product?->name : null
            ),
            'quantity' => $this->quantity,
            'unit_price' => (float) $this->unit_price,
            'subtotal' => (float) $this->subtotal,
        ];
    }
}
