<?php

namespace Database\Factories;

use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    protected $model = Vehicle::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $brands = ['Honda', 'Yamaha', 'Kawasaki', 'Suzuki'];
        $models = ['Vario 160', 'BeAT', 'NMAX 155', 'Aerox 155', 'PCX 160', 'CBR150R'];

        return [
            'brand' => fake()->randomElement($brands),
            'model' => fake()->unique()->randomElement($models).' '.fake()->randomNumber(3),
            'year_start' => fake()->numberBetween(2015, 2023),
            'year_end' => fake()->optional(0.4)->numberBetween(2024, 2026),
        ];
    }

    /**
     * Kendaraan yang masih diproduksi hingga sekarang.
     */
    public function current(): static
    {
        return $this->state(fn (array $attributes) => [
            'year_end' => null,
        ]);
    }
}
