<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->randomElement([
            'Helm', 'Aki & Kelistrikan', 'Oli & Cairan', 'Busi',
            'Pengereman', 'Ban & Velg', 'Aksesori & Body',
        ]).' '.fake()->randomNumber(3);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
        ];
    }
}
