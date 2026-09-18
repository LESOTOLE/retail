<?php

namespace App\Http\Resources;

use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Vehicle
 */
class VehicleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'brand' => $this->brand,
            'model' => $this->model,
            'full_name' => $this->full_name,
            'year_start' => $this->year_start,
            'year_end' => $this->year_end,
            'year_range' => $this->year_range,
            'vehicle_id' => $this->id,
            'notes' => $this->whenPivotLoaded('product_vehicles', fn () => $this->pivot->notes),
            'compatibility_note' => $this->whenPivotLoaded('product_vehicles', fn () => $this->pivot->notes),
        ];
    }
}
