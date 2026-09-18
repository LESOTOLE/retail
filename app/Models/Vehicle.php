<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Master kendaraan (PRD 4.1 Vehicle Compatibility Engine).
 *
 * @property int $id
 * @property string $brand
 * @property string $model
 * @property int $year_start
 * @property int|null $year_end
 */
class Vehicle extends Model
{
    /** @use HasFactory<\Database\Factories\VehicleFactory> */
    use HasFactory;

    protected $fillable = [
        'brand',
        'model',
        'year_start',
        'year_end',
    ];

    protected $appends = [
        'full_name',
        'year_range',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year_start' => 'integer',
            'year_end' => 'integer',
        ];
    }

    /**
     * Produk yang kompatibel dengan kendaraan ini.
     *
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_vehicles')
            ->withPivot('notes')
            ->withTimestamps();
    }

    /**
     * Nama lengkap kendaraan: "Honda Vario 160".
     */
    public function getFullNameAttribute(): string
    {
        return trim("{$this->brand} {$this->model}");
    }

    /**
     * Representasi rentang tahun: "2022 - sekarang".
     */
    public function getYearRangeAttribute(): string
    {
        return $this->year_end
            ? "{$this->year_start} - {$this->year_end}"
            : "{$this->year_start} - sekarang";
    }

    /**
     * Cek apakah sebuah tahun berada dalam rentang produksi.
     */
    public function coversYear(int $year): bool
    {
        return $year >= $this->year_start
            && ($this->year_end === null || $year <= $this->year_end);
    }

    /**
     * Filter berdasarkan merek.
     *
     * @param  Builder<Vehicle>  $query
     */
    public function scopeBrand(Builder $query, ?string $brand): Builder
    {
        return $query->when(
            filled($brand),
            fn (Builder $q) => $q->where('brand', $brand)
        );
    }

    /**
     * Pencarian bebas pada merek & model.
     *
     * @param  Builder<Vehicle>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return $query->when(filled($term), function (Builder $q) use ($term) {
            $keyword = '%'.$term.'%';

            $q->where(function (Builder $inner) use ($keyword) {
                $inner->where('brand', 'like', $keyword)
                    ->orWhere('model', 'like', $keyword);
            });
        });
    }

    /**
     * Filter kendaraan yang diproduksi pada tahun tertentu.
     *
     * @param  Builder<Vehicle>  $query
     */
    public function scopeForYear(Builder $query, ?int $year): Builder
    {
        return $query->when($year !== null, function (Builder $q) use ($year) {
            $q->where('year_start', '<=', $year)
                ->where(function (Builder $inner) use ($year) {
                    $inner->whereNull('year_end')
                        ->orWhere('year_end', '>=', $year);
                });
        });
    }
}
