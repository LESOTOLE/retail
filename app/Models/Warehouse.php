<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Master Cabang Toko & Gudang Logistik (PRD Phase 2 - Feature 5).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $address
 * @property string $city
 * @property string $postal_code
 * @property float $latitude
 * @property float $longitude
 * @property bool $is_active
 * @property bool $is_central
 */
class Warehouse extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'address',
        'city',
        'postal_code',
        'latitude',
        'longitude',
        'is_active',
        'is_central',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'is_active' => 'boolean',
            'is_central' => 'boolean',
        ];
    }

    /**
     * @return HasMany<WarehouseStock, $this>
     */
    public function stocks(): HasMany
    {
        return $this->hasMany(WarehouseStock::class);
    }

    /**
     * @return BelongsToMany<ProductVariant, $this>
     */
    public function variants(): BelongsToMany
    {
        return $this->belongsToMany(ProductVariant::class, 'warehouse_stocks')
            ->withPivot(['stock', 'min_stock_alert'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<StockTransfer, $this>
     */
    public function transfersFrom(): HasMany
    {
        return $this->hasMany(StockTransfer::class, 'from_warehouse_id');
    }

    /**
     * @return HasMany<StockTransfer, $this>
     */
    public function transfersTo(): HasMany
    {
        return $this->hasMany(StockTransfer::class, 'to_warehouse_id');
    }

    /**
     * Filter hanya cabang/gudang yang aktif.
     *
     * @param  Builder<Warehouse>  $query
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Hitung jarak Haversine ke titik koordinat tertentu (dalam kilometer).
     */
    public function calculateDistanceTo(float $targetLat, float $targetLng): float
    {
        $earthRadiusKm = 6371.0;

        $latFrom = deg2rad($this->latitude);
        $lonFrom = deg2rad($this->longitude);
        $latTo = deg2rad($targetLat);
        $lonTo = deg2rad($targetLng);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $angle = 2 * asin(sqrt(
            pow(sin($latDelta / 2), 2) +
            cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)
        ));

        return round($angle * $earthRadiusKm, 2);
    }
}
