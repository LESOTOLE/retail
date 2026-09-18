<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Varian produk & Safety Stock Alert (PRD 4.2).
 *
 * @property int $id
 * @property int $product_id
 * @property string $sku
 * @property string $variant_name
 * @property string $additional_price
 * @property int $stock
 * @property int $min_stock_alert
 */
class ProductVariant extends Model
{
    /** @use HasFactory<\Database\Factories\ProductVariantFactory> */
    use HasFactory;

    protected $fillable = [
        'product_id',
        'sku',
        'variant_name',
        'additional_price',
        'stock',
        'min_stock_alert',
        'weight_gram',
        'length_cm',
        'width_cm',
        'height_cm',
    ];

    protected $appends = [
        'final_price',
        'needs_restock',
        'in_stock',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'additional_price' => 'decimal:2',
            'stock' => 'integer',
            'min_stock_alert' => 'integer',
            'weight_gram' => 'integer',
            'length_cm' => 'decimal:2',
            'width_cm' => 'decimal:2',
            'height_cm' => 'decimal:2',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'sku';
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Stok multi-cabang/gudang.
     *
     * @return HasMany<WarehouseStock, $this>
     */
    public function warehouseStocks(): HasMany
    {
        return $this->hasMany(WarehouseStock::class);
    }

    /**
     * Harga final varian = base_price produk + additional_price.
     */
    public function getFinalPriceAttribute(): float
    {
        $base = (float) ($this->product?->base_price ?? 0);

        return round($base + (float) $this->additional_price, 2);
    }

    /**
     * Flag restock otomatis saat stok <= min_stock_alert (PRD 4.2).
     */
    public function getNeedsRestockAttribute(): bool
    {
        return $this->stock <= $this->min_stock_alert;
    }

    public function getInStockAttribute(): bool
    {
        return $this->stock > 0;
    }

    public function hasStockFor(int $quantity): bool
    {
        return $this->stock >= $quantity;
    }

    /**
     * Varian yang perlu direstock (dashboard admin).
     *
     * @param  Builder<ProductVariant>  $query
     */
    public function scopeLowStock(Builder $query): Builder
    {
        return $query->whereColumn('stock', '<=', 'min_stock_alert');
    }

    /**
     * Varian yang masih tersedia.
     *
     * @param  Builder<ProductVariant>  $query
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('stock', '>', 0);
    }
}
