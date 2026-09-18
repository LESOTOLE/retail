<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'brand' => $this->brand,
            'description' => $this->description,
            'base_price' => (float) $this->base_price,
            'is_active' => $this->is_active,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'variants' => ProductVariantResource::collection($this->whenLoaded('variants')),
            'compatible_vehicles' => VehicleResource::collection($this->whenLoaded('vehicles')),
            'variants_count' => $this->whenCounted('variants'),
            'total_stock' => $this->when(
                $this->relationLoaded('variants'),
                fn () => $this->total_stock
            ),
            'lowest_price' => $this->when(
                $this->relationLoaded('variants'),
                fn () => $this->lowest_price
            ),
        ];
    }
}
