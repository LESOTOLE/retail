<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeder kategori produk sesuai PRD bagian 5.
 */
class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Helm',
            'Aki & Kelistrikan',
            'Oli & Cairan',
            'Busi',
            'Pengereman',
            'Ban & Velg',
            'Aksesori & Body',
        ];

        foreach ($categories as $name) {
            Category::updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name]
            );
        }
    }
}
