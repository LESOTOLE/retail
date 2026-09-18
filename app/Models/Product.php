<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Produk induk katalog (PRD 5 - products).
 *
 * @property int $id
 * @property int $category_id
 * @property string $name
 * @property string $slug
 * @property string $brand
 * @property string|null $description
 * @property string $base_price
 * @property bool $is_active
 */
class Product extends Model
{
    /** @use HasFactory<\Database\Factories\ProductFactory> */
    use HasFactory;

    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'brand',
        'description',
        'base_price',
        'weight_gram',
        'length_cm',
        'width_cm',
        'height_cm',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
            'weight_gram' => 'integer',
            'length_cm' => 'decimal:2',
            'width_cm' => 'decimal:2',
            'height_cm' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Product $product): void {
            if (blank($product->slug)) {
                $product->slug = Str::slug($product->name);
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Varian produk; ikut terhapus saat produk dihapus (cascade di DB).
     *
     * @return HasMany<ProductVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /**
     * Kendaraan yang kompatibel dengan produk ini.
     *
     * @return BelongsToMany<Vehicle, $this>
     */
    public function vehicles(): BelongsToMany
    {
        return $this->belongsToMany(Vehicle::class, 'product_vehicles')
            ->withPivot('notes')
            ->withTimestamps();
    }

    /**
     * Kendaraan yang kompatibel dengan produk ini (alias dari vehicles).
     *
     * @return BelongsToMany<Vehicle, $this>
     */
    public function compatibleVehicles(): BelongsToMany
    {
        return $this->vehicles();
    }

    /**
     * Total stok seluruh varian.
     */
    public function getTotalStockAttribute(): int
    {
        return (int) $this->variants->sum('stock');
    }

    /**
     * Harga termurah (base + additional terkecil).
     */
    public function getLowestPriceAttribute(): float
    {
        $minAdditional = (float) ($this->variants->min('additional_price') ?? 0);

        return (float) $this->base_price + $minAdditional;
    }

    /**
     * Hanya produk aktif.
     *
     * @param  Builder<Product>  $query
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Produk yang kompatibel dengan kendaraan tertentu (PRD 6.2 ?vehicle_id=).
     *
     * @param  Builder<Product>  $query
     */
    public function scopeForVehicle(Builder $query, int|string|null $vehicleId): Builder
    {
        return $query->when(filled($vehicleId), function (Builder $q) use ($vehicleId) {
            $q->whereHas('vehicles', fn (Builder $v) => $v->where('vehicles.id', $vehicleId));
        });
    }

    /**
     * Filter kategori berdasarkan id maupun slug.
     *
     * @param  Builder<Product>  $query
     */
    public function scopeForCategory(Builder $query, int|string|null $category): Builder
    {
        return $query->when(filled($category), function (Builder $q) use ($category) {
            if (is_numeric($category)) {
                $q->where('category_id', (int) $category);

                return;
            }

            $q->whereHas('category', fn (Builder $c) => $c->where('slug', $category));
        });
    }

    /**
     * Pencarian nama, brand, deskripsi, dan SKU varian.
     *
     * @param  Builder<Product>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return $query->when(filled($term), function (Builder $q) use ($term) {
            $keyword = '%'.$term.'%';

            $q->where(function (Builder $inner) use ($keyword) {
                $inner->where('name', 'like', $keyword)
                    ->orWhere('brand', 'like', $keyword)
                    ->orWhere('description', 'like', $keyword)
                    ->orWhereHas('category', fn (Builder $c) => $c->where('name', 'like', $keyword)->orWhere('slug', 'like', $keyword))
                    ->orWhereHas('variants', fn (Builder $v) => $v->where('sku', 'like', $keyword));
            });
        });
    }

    /**
     * Rentang harga berdasarkan base_price.
     *
     * @param  Builder<Product>  $query
     */
    public function scopePriceBetween(Builder $query, int|float|null $min, int|float|null $max): Builder
    {
        return $query
            ->when($min !== null, fn (Builder $q) => $q->where('base_price', '>=', $min))
            ->when($max !== null, fn (Builder $q) => $q->where('base_price', '<=', $max));
    }
}
