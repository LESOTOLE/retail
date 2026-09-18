<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'sku' => strtoupper(fake()->unique()->bothify('MV-????-####')),
            'variant_name' => fake()->randomElement(['Ukuran M', 'Ukuran L', 'Ukuran XL', '1 Liter', '0.8 Liter', 'Standard']),
            'additional_price' => fake()->randomElement([0, 5_000, 15_000, 25_000]),
            'stock' => fake()->numberBetween(0, 60),
            'min_stock_alert' => 5,
        ];
    }

    /**
     * Varian dengan stok di bawah batas aman (PRD 4.2).
     */
    public function lowStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock' => 2,
            'min_stock_alert' => 5,
        ]);
    }

    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock' => 0,
        ]);
    }

    public function withStock(int $stock): static
    {
        return $this->state(fn (array $attributes) => [
            'stock' => $stock,
        ]);
    }
}
