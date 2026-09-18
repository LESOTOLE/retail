<?php

namespace App\Http\Resources;

use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProductVariant
 */
class ProductVariantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'variant_name' => $this->variant_name,
            'additional_price' => (float) $this->additional_price,
            'final_price' => $this->final_price,
            'stock' => $this->stock,
            'min_stock_alert' => $this->min_stock_alert,
            'in_stock' => $this->in_stock,
            // Safety Stock Alert (PRD 4.2)
            'needs_restock' => $this->needs_restock,
        ];
    }
}
